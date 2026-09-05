<?php
/**
 * HTL_Renderer
 * ---------------------------------------------------------------------
 * É aqui que "ignorar o tema" acontece de verdade, via o filtro
 * `template_include`. Cobre dois cenários bem diferentes:
 *
 *   1. Posts/páginas individuais — template escolhido por post, na
 *      metabox (HTL_Metabox::META_TEMPLATE_ID).
 *   2. Home, arquivos, busca e 404 — não existe um "post" pra pendurar
 *      essa escolha, então ela mora numa tela de Ajustes própria
 *      (HTL_Settings), guardada como uma option só.
 *
 * Ordem de processamento do HTML de um template, de fora pra dentro:
 *   1. {{assets_url}}      — pasta de assets do template raiz (cada
 *                            {{include}} resolve a sua própria depois).
 *   2. resolve_includes()  — compõe {{include:slug}} (outros templates).
 *   3. resolve_menus()     — expande {{menu location="..."}} com o menu
 *                            nativo do WordPress.
 *   4. resolve_meta_outside_loops() — {{meta:}}/{{meta_url:}} resolvidos
 *                            SOMENTE no HTML autoriado do template e fora
 *                            de corpos de {{loop}} (cada corpo ganha
 *                            resolução própria por post, dentro do loop).
 *                            O output gerado nunca é re-escaneado: texto
 *                            de post contendo o literal {{meta:x}} não
 *                            resolve nada — é o que impede injeção de
 *                            segunda ordem via conteúdo de baixo
 *                            privilégio (que o core não passa por kses).
 *   5. resolve_loops()     — expande {{loop ...}}...{{/loop}} em listas
 *                            de posts/categorias reais do WordPress,
 *                            já filtrando automaticamente pela
 *                            categoria/tag/autor/busca atual quando o
 *                            template está sendo usado como arquivo.
 *   6. replace_tags()      — troca as tags que sobraram: no contexto de
 *                            UM post (posts/páginas) ou no contexto
 *                            "sem post" de um arquivo/home/busca/404.
 *   7. do_shortcode()      — processa shortcodes de plugins (formulários
 *                            etc.) colados no HTML do template.
 */

defined( 'ABSPATH' ) || exit;

class HTL_Renderer {

	/** Profundidade máxima de {{include:slug}} aninhados — proteção
	 *  contra loop infinito (A inclui B, B inclui A, e por aí vai). */
	const MAX_INCLUDE_DEPTH = 5;

	/** Teto de posts por {{loop}} — protege contra alguém pedir uma
	 *  quantidade absurda e travar o servidor numa consulta gigante. */
	const MAX_LOOP_POSTS = 50;

	public function __construct() {
		// Prioridade 99: garante que rodamos DEPOIS de outros plugins que
		// também mexam em template_include, então nossa decisão é a
		// última palavra quando há um template válido escolhido.
		add_filter( 'template_include', array( $this, 'maybe_override_template' ), 99 );
	}

	public function maybe_override_template( $template ) {
		// Pré-visualização de um template (ver HTL_Metabox::render_editor()
		// -> botão "Pré-visualizar template"). Roda ANTES de tudo porque a
		// URL de preview não precisa corresponder a nenhum post/página
		// real — ela usa a home como âncora só pra ter uma URL válida.
		if ( isset( $_GET['htl_preview'] ) ) {
			$this->maybe_render_preview();
			// Se chegou até aqui, a pré-visualização foi recusada (sem
			// permissão, ID inválido) — segue o fluxo normal abaixo.
		}

		// Caso 1: post/página individual — template escolhido na
		// metabox daquele post específico. A escolha manual vence; sem
		// escolha, valem as regras globais (Ajustes → "Posts e páginas
		// por regra").
		if ( is_singular() ) {
			$post_id = get_queried_object_id();

			$template_id = (int) get_post_meta( $post_id, HTL_Metabox::META_TEMPLATE_ID, true );

			if ( ! $this->template_is_valid( $template_id ) ) {
				$template_id = $this->resolve_rule_template( $post_id );
			}

			if ( ! $this->template_is_valid( $template_id ) ) {
				return $template; // Nada escolhido e nenhuma regra casa: tema normal cuida da página.
			}

			$this->render_page( $post_id, $template_id );
			exit; // Já imprimimos a página inteira — não deixa o WP continuar o fluxo normal.
		}

		// Caso 2: home, arquivo, busca ou 404 — template escolhido
		// globalmente na tela de Ajustes (HTL_Settings), não por post.
		$template_id = $this->resolve_archive_template();

		if ( $this->template_is_valid( $template_id ) ) {
			// $post_id = 0: não existe UM post específico sendo
			// visitado — replace_tags() trata esse caso com tags de
			// arquivo ({{archive_title}}, etc.) em vez das tags de post.
			$this->render_page( 0, $template_id );
			exit;
		}

		return $template;
	}

	/**
	 * Confere se um ID de template ainda é válido pra usar — existe,
	 * é do tipo certo, e está publicado. Centralizado aqui porque três
	 * lugares diferentes (post individual, arquivo, preview) precisam
	 * da mesma checagem.
	 */
	private function template_is_valid( $template_id ) {
		return $template_id
			&& HTL_Post_Type::SLUG === get_post_type( $template_id )
			&& 'publish' === get_post_status( $template_id );
	}

	/**
	 * Casa o post atual com as regras globais salvas em HTL_Settings
	 * (option htl_singular_rules). A primeira regra que casar vence —
	 * a ordem das linhas na tela de Ajustes importa. Regra com categoria
	 * vazia casa com qualquer post do tipo; se o tipo não usar a
	 * taxonomia "category", a categoria da regra é ignorada.
	 */
	private function resolve_rule_template( $post_id ) {
		$rules = get_option( 'htl_singular_rules', array() );

		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return 0;
		}

		$post_type = get_post_type( $post_id );

		foreach ( $rules as $rule ) {
			if ( empty( $rule['post_type'] ) || $rule['post_type'] !== $post_type ) {
				continue;
			}

			if ( ! empty( $rule['category'] ) ) {
				if ( ! is_object_in_taxonomy( $post_type, 'category' ) || ! has_category( $rule['category'], $post_id ) ) {
					continue;
				}
			}

			return (int) $rule['template_id'];
		}

		return 0;
	}

	/**
	 * Olha a option salva por HTL_Settings e decide se a URL atual
	 * (home, categoria, tag, autor, data, busca, 404) tem um template
	 * configurado. A ordem do array importa: do mais específico pro
	 * mais genérico — mesmo espírito da hierarquia de templates nativa
	 * do WordPress (um 404 é checado antes de "home", por exemplo,
	 * mesmo que tecnicamente as duas condições nunca sejam
	 * simultaneamente verdadeiras na prática).
	 */
	private function resolve_archive_template() {
		$option = get_option( HTL_Settings::OPTION_KEY, array() );

		if ( empty( $option ) || ! is_array( $option ) ) {
			return 0;
		}

		$conditions = apply_filters(
			'htl_archive_conditions',
			array(
				'404'      => 'is_404',
				'search'   => 'is_search',
				'author'   => 'is_author',
				'date'     => 'is_date',
				'tag'      => 'is_tag',
				'category' => 'is_category',
				'home'     => 'is_home',
			)
		);

		foreach ( $conditions as $key => $condition ) {
			if ( empty( $option[ $key ] ) || ! is_callable( $condition ) ) {
				continue;
			}

			if ( call_user_func( $condition ) ) {
				return (int) $option[ $key ];
			}
		}

		return 0;
	}

	/**
	 * URL da pasta de assets de um template — uploads/htl-templates/{slug}/.
	 * Os arquivos são enviados por FTP/gerenciador de arquivos do host (o
	 * plugin não faz upload, mantendo a superfície mínima); a pasta é
	 * criada no salvamento do template. Filtrável via htl_assets_url.
	 */
	private function assets_url( $template_id ) {
		$slug = (string) get_post_field( 'post_name', $template_id );

		if ( '' === $slug ) {
			return '';
		}

		$upload_dir = wp_upload_dir();
		$url        = trailingslashit( $upload_dir['baseurl'] ) . 'htl-templates/' . $slug;

		return esc_url_raw( apply_filters( 'htl_assets_url', $url, $template_id ) );
	}

	/**
	 * Pré-visualização de um template — não exige uma página real
	 * associada. `?htl_preview=<id>` mostra o template usando ele mesmo
	 * como contexto (então {{post_title}} mostra o título do template);
	 * `&htl_preview_context=<post_id>` deixa ver com dado de um post
	 * real, se quiser.
	 */
	private function maybe_render_preview() {
		// Preview é ferramenta de admin: sem usuário logado não faz sentido
		// processar (a checagem de capability abaixo já cobriria, mas o
		// guard explícito impede que plugins de cache agressivos sirvam
		// `/?htl_preview=N` a visitantes anônimos).
		if ( ! is_user_logged_in() ) {
			return;
		}

		$template_id = absint( $_GET['htl_preview'] );

		if ( ! $this->template_is_valid( $template_id ) ) {
			return;
		}

		// Mesma checagem de permissão que protege a tela de edição do
		// template — só quem já pode editá-lo pode pré-visualizá-lo.
		if ( ! current_user_can( 'edit_post', $template_id ) ) {
			return;
		}

		$context_id = isset( $_GET['htl_preview_context'] ) ? absint( $_GET['htl_preview_context'] ) : $template_id;
		if ( ! get_post( $context_id ) ) {
			$context_id = $template_id;
		}

		// Reforça no-cache: a resposta contém conteúdo de rascunho e não
		// deve parar em nenhum cache de página (Varnish, plugin, CDN).
		nocache_headers();

		$this->render_page( $context_id, $template_id );
		exit;
	}

	private function render_page( $post_id, $template_id ) {
		// Só faz sentido checar senha quando existe UM post real sendo
		// visitado — em home/arquivo/busca ($post_id === 0) não há post
		// nenhum pra proteger aqui.
		if ( $post_id && post_password_required( $post_id ) ) {
			$this->render_password_form( $post_id );
			return;
		}

		$css_bucket = array();
		$own_css    = get_post_meta( $template_id, HTL_Metabox::META_CSS, true );

		if ( ! empty( $own_css ) ) {
			$css_bucket[] = $own_css;
		}

		// Cada template tem a própria pasta de assets
		// (uploads/htl-templates/{slug}/): o template raiz é resolvido
		// aqui; o de cada {{include}} é resolvido dentro do próprio
		// include — é o que faz um header/footer reusável apontar pros
		// SEUS arquivos, não pros do template pai.
		$html = strtr(
			get_post_meta( $template_id, HTL_Metabox::META_HTML, true ),
			array( '{{assets_url}}' => $this->assets_url( $template_id ) )
		);

		$html = $this->resolve_includes( $html, $css_bucket );
		$html = $this->resolve_menus( $html );

		// Resolução de meta SOMENTE sobre HTML autoriado, fora de corpos
		// de {{loop}} — cada corpo resolve a própria meta por post dentro
		// de resolve_loops(). O output de expansão, que embute conteúdo de
		// posts de autores comuns (dado que o core não passa por kses no
		// save), nunca volta pelo parser de meta: um post contendo o
		// literal "{{meta:evil}}" não injeta resolução na passada externa
		// (stored XSS de segunda ordem).
		$html = $this->resolve_meta_outside_loops( $html, $post_id );

		$html = $this->resolve_loops( $html );
		$html = $this->replace_tags( $html, $post_id );

		// Escape hatch pra formulários e componentes de plugins: shortcodes
		// colados no HTML do template são processados como num post normal
		// (Contact Form 7, Gravity Forms, etc.). Shortcode não registrado
		// permanece como texto, igual no core.
		$html = do_shortcode( $html );

		$this->print_shell( $html, implode( "\n", $css_bucket ) );
	}

	private function render_password_form( $post_id ) {
		$this->print_shell( get_the_password_form( $post_id ) );
	}

	/**
	 * Troca {{include:slug}} pelo HTML de outro post "htl_template",
	 * recursivamente, acumulando o CSS de cada um em $css_bucket (por
	 * referência) pra ser impresso uma única vez no <head>.
	 */
	private function resolve_includes( $html, array &$css_bucket, array $visited = array(), $depth = 0 ) {
		if ( $depth > self::MAX_INCLUDE_DEPTH ) {
			return $html;
		}

		return preg_replace_callback(
			'/\{\{include:([a-z0-9-]+)\}\}/i',
			function ( $matches ) use ( &$css_bucket, $visited, $depth ) {
				$slug = $matches[1];

				if ( in_array( $slug, $visited, true ) ) {
					return sprintf( __( '<!-- htl: inclusion loop detected in "%s" -->', 'html-templates-lite' ), esc_html( $slug ) );
				}

				$included = get_page_by_path( $slug, OBJECT, HTL_Post_Type::SLUG );

				if ( ! $included || 'publish' !== $included->post_status ) {
					return sprintf( __( '<!-- htl: template "%s" not found or not published -->', 'html-templates-lite' ), esc_html( $slug ) );
				}

				$included_css = get_post_meta( $included->ID, HTL_Metabox::META_CSS, true );
				if ( ! empty( $included_css ) ) {
					$css_bucket[] = $included_css;
				}

				$included_html = get_post_meta( $included->ID, HTL_Metabox::META_HTML, true );

				// Assets do template incluído apontam pra pasta DELE, não
				// pra do template raiz — é isso que torna um header/footer
				// reusável autossuficiente.
				$included_html = strtr(
					$included_html,
					array( '{{assets_url}}' => $this->assets_url( $included->ID ) )
				);

				return $this->resolve_includes(
					$included_html,
					$css_bucket,
					array_merge( $visited, array( $slug ) ),
					$depth + 1
				);
			},
			$html
		);
	}

	/**
	 * Expande {{menu location="primary"}} pelo menu nativo do WordPress.
	 * Sem wrapper: devolve o <ul> com as classes nativas (menu,
	 * menu-item, current-menu-item...) pra encaixar na marcação do
	 * template — a <nav> e o CSS ficam por conta do autor do template.
	 */
	private function resolve_menus( $html ) {
		return preg_replace_callback(
			'/\{\{menu\b\s*(.*?)\}\}/i',
			function ( $matches ) {
				$atts     = shortcode_parse_atts( trim( $matches[1] ) );
				$location = ( is_array( $atts ) && ! empty( $atts['location'] ) ) ? sanitize_key( $atts['location'] ) : '';

				if ( '' === $location ) {
					return __( '<!-- htl: missing menu location, e.g. {{menu location="primary"}} -->', 'html-templates-lite' );
				}

				if ( ! has_nav_menu( $location ) ) {
					return sprintf(
						__( '<!-- htl: menu "%s" has no menu assigned (Appearance → Menus) -->', 'html-templates-lite' ),
						esc_html( $location )
					);
				}

				$menu = wp_nav_menu(
					array(
						'theme_location' => $location,
						'echo'           => false,
						'container'      => false,
					)
				);

				return is_string( $menu ) ? $menu : '';
			},
			$html
		);
	}

	/**
	 * Expande {{loop attrs...}}...{{/loop}} numa lista real de posts do
	 * WordPress. Os atributos usam a MESMA sintaxe de um shortcode
	 * (key="value"), reaproveitando shortcode_parse_atts() do core em
	 * vez de escrever um parser próprio.
	 *
	 * Atributos aceitos: post_type, category, tag, author, s (busca),
	 * count, orderby, order, paged.
	 *
	 * Auto-detecção pra templates de arquivo: se o template estiver
	 * sendo usado como template de categoria/tag/autor/busca (via
	 * HTL_Settings) e o atributo correspondente não for informado no
	 * {{loop}}, ele é preenchido sozinho com a categoria/tag/autor/busca
	 * ATUAL da URL. Um valor explícito no {{loop}} sempre tem prioridade
	 * sobre esse auto-preenchimento.
	 */
	private function resolve_loops( $html ) {
		return preg_replace_callback(
			'/\{\{loop\s*(.*?)\}\}(.*?)\{\{\/loop\}\}/is',
			function ( $matches ) {
				$attr_string    = trim( $matches[1] );
				$inner_template = $matches[2];

				$atts = shortcode_parse_atts( $attr_string );
				if ( ! is_array( $atts ) ) {
					$atts = array();
				}

				$atts = wp_parse_args(
					$atts,
					array(
						'post_type' => 'post',
						'category'  => '',
						'tag'       => '',
						'author'    => '',
						's'         => '',
						'count'     => 5,
						'orderby'   => 'date',
						'order'     => 'DESC',
						'paged'     => false,
					)
				);

				// Preenche sozinho a partir do arquivo atual, só quando o
				// autor do template não especificou o atributo — é isso
				// que deixa usar o MESMO template de categoria pra
				// qualquer categoria, sem repetir o slug na mão.
				if ( '' === $atts['category'] && is_category() ) {
					$atts['category'] = get_queried_object()->slug;
				}
				if ( '' === $atts['tag'] && is_tag() ) {
					$atts['tag'] = get_queried_object()->slug;
				}
				if ( '' === $atts['author'] && is_author() ) {
					$atts['author'] = get_queried_object_id();
				}
				if ( '' === $atts['s'] && is_search() ) {
					$atts['s'] = get_search_query();
				}

				$query_args = array(
					'post_type'      => sanitize_key( $atts['post_type'] ),
					'posts_per_page' => min( self::MAX_LOOP_POSTS, max( 1, absint( $atts['count'] ) ) ),
					'orderby'        => sanitize_key( $atts['orderby'] ),
					'order'          => ( 'ASC' === strtoupper( $atts['order'] ) ) ? 'ASC' : 'DESC',
					// Não precisamos do total de posts pra paginação
					// aqui — pular essa contagem evita uma consulta
					// SQL extra desnecessária.
					'no_found_rows'  => true,
				);

				if ( '' !== $atts['category'] ) {
					$query_args['category_name'] = sanitize_title( $atts['category'] );
				}

				if ( '' !== $atts['tag'] ) {
					$query_args['tag'] = sanitize_title( $atts['tag'] );
				}

				if ( '' !== $atts['author'] ) {
					$query_args['author'] = absint( $atts['author'] );
				}

				if ( '' !== $atts['s'] ) {
					$query_args['s'] = sanitize_text_field( $atts['s'] );
				}

				// paged="true" (ou "1"): segue a paginação da URL atual
				// (/page/2/, /page/3/...). Destinado a templates de arquivo,
				// onde o loop lista o mesmo conteúdo do contexto — em loops
				// "widget" com filtros próprios não corresponde ao que ele
				// exibe, por isso é opt-in e não default. Precisa do total
				// de posts pra calcular as páginas, então abre mão do
				// no_found_rows neste caso.
				if ( in_array( (string) $atts['paged'], array( 'true', '1' ), true ) ) {
					$query_args['paged']         = max( 1, (int) get_query_var( 'paged' ) );
					$query_args['no_found_rows'] = false;
				}

				/**
				 * Deixa devs ajustarem a consulta do loop sem editar o
				 * plugin — por exemplo, pra filtrar por uma taxonomia
				 * customizada, ou por ano/mês num template de arquivo de
				 * data (que esta versão não auto-detecta sozinha):
				 *
				 *     add_filter( 'htl_loop_query_args', function ( $args, $atts ) {
				 *         if ( is_date() ) {
				 *             $args['year']     = get_query_var( 'year' );
				 *             $args['monthnum'] = get_query_var( 'monthnum' );
				 *         }
				 *         return $args;
				 *     }, 10, 2 );
				 */
				$query_args = apply_filters( 'htl_loop_query_args', $query_args, $atts );

				// Templates não podem ser listados em loop — exporia os
				// títulos de outros templates em páginas públicas. Checado
				// DEPOIS do filtro acima, pra nenhum caminho furar a regra.
				if ( ! empty( $query_args['post_type'] ) && 'htl_template' === $query_args['post_type'] ) {
					return __( '<!-- htl: templates cannot be listed in a {{loop}} -->', 'html-templates-lite' );
				}

				$query  = new WP_Query( $query_args );
				$output = '';

				foreach ( $query->posts as $looped_post ) {
					// Meta resolvida no TEMPLATE INTERNO (autoriado) contra
					// o post da iteração — o output da expansão, que embute
					// conteúdo de posts de autores comuns, nunca passa de
					// novo pelo parser de meta (injeção de segunda ordem).
					$inner   = $this->resolve_meta_tags( $inner_template, $looped_post->ID );
					$output .= $this->replace_tags( $inner, $looped_post->ID );
				}

				if ( '' === $output ) {
					// Nenhum post encontrado — comentário visível em vez
					// de o bloco simplesmente desaparecer sem explicação.
					$output = sprintf( __( '<!-- htl: no posts found for this loop (%s) -->', 'html-templates-lite' ), esc_html( $attr_string ) );
				}

				return $output;
			},
			$html
		);
	}

	/**
	 * Renderiza a tag {{pagination}} — a navegação entre páginas da URL
	 * atual (página 2, 3... de home/categoria/tag/autor/data/busca), com
	 * base na consulta principal do WordPress. Combina com
	 * {{loop ... paged="true"}}: na página 2, o loop passa a listar os
	 * posts seguintes em vez de repetir os primeiros.
	 *
	 * Em contextos com uma página só, retorna string vazia — a tag some
	 * em vez de imprimir uma navegação órfã.
	 */
	private function render_pagination() {
		global $wp_query;

		if ( ! $wp_query || (int) $wp_query->max_num_pages <= 1 ) {
			return '';
		}

		$links = paginate_links(
			array(
				'total'     => (int) $wp_query->max_num_pages,
				'current'   => max( 1, (int) get_query_var( 'paged' ) ),
				'mid_size'  => 2,
				'prev_text' => __( '&laquo; Previous', 'html-templates-lite' ),
				'next_text' => __( 'Next &raquo;', 'html-templates-lite' ),
				'type'      => 'list',
			)
		);

		if ( ! is_string( $links ) || '' === $links ) {
			return '';
		}

		return '<nav class="htl-pagination" aria-label="' . esc_attr( __( 'Posts pagination', 'html-templates-lite' ) ) . '">' . $links . '</nav>';
	}

	/**
	 * Resolve {{meta:chave}}/{{meta_url:chave}} numa string AUTORIADA
	 * (template raiz ou corpo interno de um {{loop}}) contra $post_id.
	 *
	 * A saída é SEMPRE escapada: o valor do campo é dado do autor do
	 * post — que normalmente NÃO tem unfiltered_html, e o core não passa
	 * post meta por kses no save (diferente de título/conteúdo/excerto).
	 *
	 *   - {{meta:chave}}     → esc_html (texto e atributos);
	 *   - {{meta_url:chave}} → esc_url (href/src; neutraliza javascript:).
	 *
	 * Não existe variante "crua" de propósito — seria reabrir a rota de
	 * XSS. Casos especiais: filtro htl_template_tags.
	 */
	private function resolve_meta_tags( $html, $post_id ) {
		if ( ! $post_id ) {
			return $html;
		}

		return preg_replace_callback(
			'/\{\{(meta(?:_url)?):([a-zA-Z0-9_-]+)\}\}/',
			function ( $matches ) use ( $post_id ) {
				return $this->resolve_meta_tag( $matches[1], $matches[2], $post_id );
			},
			$html
		);
	}

	/**
	 * Passada de meta do template raiz: resolve as tags da região
	 * autoriada, PULANDO corpos de {{loop}} — a iteração resolve a meta
	 * dela própria, contra o post da iteração (resolve_loops), e o output
	 * da expansão não volta pelo parser. Sem esse pulo, um post cujo
	 * conteúdo contivesse o literal "{{meta:evil}}" imprimia o meta do
	 * post visitado, cru, pra todo visitante.
	 */
	private function resolve_meta_outside_loops( $html, $post_id ) {
		if ( ! $post_id ) {
			return $html;
		}

		return preg_replace_callback(
			'/\{\{loop\b.*?\{\{\/loop\}\}|\{\{(meta(?:_url)?):([a-zA-Z0-9_-]+)\}\}/is',
			function ( $matches ) use ( $post_id ) {
				// Alternância: grupo 2 só existe quando o match é tag de
				// meta; um bloco {{loop}} inteiro volta intocado.
				if ( ! isset( $matches[2] ) ) {
					return $matches[0];
				}

				return $this->resolve_meta_tag( $matches[1], $matches[2], $post_id );
			},
			$html
		);
	}

	/**
	 * Resolve UMA tag de meta. Guards em ordem: senha > chave protegida >
	 * escape do valor.
	 */
	private function resolve_meta_tag( $type, $key, $post_id ) {
		// Meta é conteúdo do post: em posts protegidos por senha, nada
		// sai — nem dentro de {{loop}} (o singular com senha nem chega
		// aqui: render_password_form() intercepta antes).
		if ( post_password_required( $post_id ) ) {
			return __( '<!-- htl: meta of a password-protected post is not exposed -->', 'html-templates-lite' );
		}

		// is_protected_meta cobre o prefixo "_" nativo E as chaves que
		// plugins marcarem como protegidas — mais completo que checar só
		// o prefixo.
		if ( is_protected_meta( $key, 'post' ) ) {
			return __( '<!-- htl: protected meta keys are not exposed -->', 'html-templates-lite' );
		}

		$value = get_post_meta( $post_id, $key, true );

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return 'meta_url' === $type
			? esc_url( (string) $value )
			: esc_html( (string) $value );
	}

	/**
	 * {{comment_form}} — formulário de comentários nativo do WordPress.
	 * Some quando comentários estão fechados ou o tipo de conteúdo não
	 * os suporta.
	 */
	private function render_comment_form( $post_id ) {
		if ( ! post_type_supports( get_post_type( $post_id ), 'comments' ) || ! comments_open( $post_id ) ) {
			return '';
		}

		ob_start();
		comment_form( array(), $post_id );

		return ob_get_clean();
	}

	/**
	 * {{comments_list}} — comentários aprovados do post, com a marcação
	 * e as classes nativas do WordPress (cada comentário num <div>).
	 */
	private function render_comments_list( $post_id ) {
		if ( ! post_type_supports( get_post_type( $post_id ), 'comments' ) ) {
			return '';
		}

		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);

		if ( empty( $comments ) ) {
			return '';
		}

		ob_start();
		wp_list_comments(
			array(
				'style'       => 'div',
				'short_ping'  => true,
				'avatar_size' => 48,
			),
			$comments
		);

		return ob_get_clean();
	}

	/**
	 * Troca {{tag}} pelo valor real. Dois modos:
	 *
	 *   - $post_id truthy: contexto de UM post (a página visitada, ou
	 *     cada iteração de um {{loop}}) — {{post_title}}, {{permalink}}, etc.
	 *   - $post_id === 0: contexto de arquivo/home/busca/404, sem um
	 *     post específico — {{archive_title}}, {{search_query}}, etc.
	 *
	 * Filtrável (htl_template_tags) pra quem quiser adicionar tags
	 * próprias sem editar o plugin:
	 *
	 *     add_filter( 'htl_template_tags', function ( $tags, $post_id ) {
	 *         if ( $post_id ) {
	 *             $tags['{{preco}}'] = get_post_meta( $post_id, 'preco', true );
	 *         }
	 *         return $tags;
	 *     }, 10, 2 );
	 */
	private function replace_tags( $html, $post_id ) {
		if ( ! $post_id ) {
			$tags = apply_filters(
				'htl_template_tags',
				array(
					'{{archive_title}}'       => get_the_archive_title(),
					'{{archive_description}}' => get_the_archive_description(),
					'{{search_query}}'        => get_search_query(),
					'{{pagination}}'          => $this->render_pagination(),
					'{{site_title}}'          => get_bloginfo( 'name' ),
					'{{site_tagline}}'        => get_bloginfo( 'description' ),
					'{{current_year}}'        => date_i18n( 'Y' ),
				),
				$post_id
			);

			return strtr( $html, $tags );
		}

		global $post;

		$original_post = $post; // Guarda o post global atual pra restaurar depois.
		$context_post  = get_post( $post_id );

		if ( $context_post ) {
			// Alguns plugins que filtram 'the_content' (por exemplo, pra
			// processar shortcodes) esperam encontrar o post certo no
			// global $post, em vez de usar só o argumento do filtro.
			// setup_postdata() garante isso — importante sobretudo
			// dentro de um {{loop}}, onde $post_id muda a cada iteração
			// e é diferente da página que está sendo visitada.
			setup_postdata( $context_post );
		}

		// Posts protegidos por senha: o conteúdo nunca é exposto — mostra
		// o formulário de senha nativo, mesmo comportamento de
		// the_content() no loop do core. O contexto singular já é filtrado
		// antes (render_page()), mas dentro de um {{loop}} cada post
		// precisa da checagem própria — get_post_field() ignora a senha.
		$post_content = post_password_required( $post_id )
			? get_the_password_form( $post_id )
			: apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );

		$tags = apply_filters(
			'htl_template_tags',
			array(
				'{{post_title}}'      => get_the_title( $post_id ),
				'{{post_content}}'    => $post_content,
				'{{post_excerpt}}'    => get_the_excerpt( $post_id ),
				'{{post_date}}'       => get_the_date( '', $post_id ),
				'{{post_author}}'     => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ),
				'{{post_categories}}' => get_the_category_list( ', ', '', $post_id ),
				'{{post_tags}}'       => get_the_tag_list( '', ', ', '', $post_id ),
				'{{featured_image}}'  => (string) get_the_post_thumbnail_url( $post_id, 'full' ),
				'{{permalink}}'       => get_permalink( $post_id ),
				'{{comment_form}}'    => $this->render_comment_form( $post_id ),
				'{{comments_list}}'   => $this->render_comments_list( $post_id ),
				'{{site_title}}'      => get_bloginfo( 'name' ),
				'{{site_tagline}}'    => get_bloginfo( 'description' ),
				'{{current_year}}'    => date_i18n( 'Y' ),
			),
			$post_id
		);

		// Restaura o post global pro que estava antes de mexermos nele —
		// essencial quando este método roda várias vezes dentro de um
		// {{loop}}, pra não "vazar" o post errado pro resto do template.
		$post = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		if ( $original_post instanceof WP_Post ) {
			setup_postdata( $original_post );
		}

		return strtr( $html, $tags );
	}

	/**
	 * Monta o HTML final da página — usado tanto pro template normal
	 * quanto pro formulário de senha, então o <head>/<body> ficam
	 * consistentes nos dois casos.
	 */
	private function print_shell( $body_html, $css = '' ) {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		}
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php
	// wp_head() é o gancho que outros plugins esperam encontrar em
	// QUALQUER página do WordPress — SEO, analytics, pixels de
	// conversão, etc. Chamando ele aqui, esses plugins continuam
	// funcionando mesmo sem o tema estar envolvido.
	wp_head();
	?>
	<?php if ( ! empty( $css ) ) : ?>
	<style id="htl-inline-css"><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput -- ver nota de segurança logo abaixo. ?></style>
	<?php endif; ?>
</head>
<body <?php body_class( 'htl-template' ); ?>>
	<?php
	// wp_body_open() é o hook que a admin bar, skip links de
	// acessibilidade e alguns plugins de analytics esperam encontrar
	// logo após a abertura do <body>.
	wp_body_open();

	/*
	 * ---------------------------------------------------------------
	 * Nota para quem revisar este plugin na fila do WordPress.org: a
	 * linha abaixo, e a linha do <style> acima, fazem `echo` de HTML e
	 * CSS SEM esc_html/wp_kses no momento da IMPRESSÃO. Isso é
	 * intencional — é a funcionalidade central do plugin, no mesmo
	 * espírito do bloco nativo "HTML personalizado" do WordPress e do
	 * painel "CSS Adicional" do Customizador, que também imprimem o
	 * conteúdo do autor sem escapar.
	 *
	 * A garantia de segurança não fica aqui — fica no momento da
	 * GRAVAÇÃO, em HTL_Metabox::save_template_content(). Lá, o HTML só
	 * é salvo 100% livre se o usuário logado tiver a capability
	 * `unfiltered_html` (administradores, por padrão, em instalação
	 * single-site); qualquer outro papel tem o conteúdo passado por
	 * wp_kses_post antes de ser salvo — a mesma sanitização que o
	 * próprio WordPress usa no conteúdo normal de um post.
	 * ---------------------------------------------------------------
	 */
	echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitizado na gravação, ver nota acima.
	wp_footer();
	?>
</body>
</html>
		<?php
	}
}
