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
 *   1. resolve_includes()  — compõe {{include:slug}} (outros templates).
 *   2. resolve_loops()     — expande {{loop ...}}...{{/loop}} em listas
 *                            de posts/categorias reais do WordPress,
 *                            já filtrando automaticamente pela
 *                            categoria/tag/autor/busca atual quando o
 *                            template está sendo usado como arquivo.
 *   3. replace_tags()      — troca as tags que sobraram: no contexto de
 *                            UM post (posts/páginas) ou no contexto
 *                            "sem post" de um arquivo/home/busca/404.
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
		// metabox daquele post específico.
		if ( is_singular() ) {
			$post_id     = get_queried_object_id();
			$template_id = (int) get_post_meta( $post_id, HTL_Metabox::META_TEMPLATE_ID, true );

			if ( ! $this->template_is_valid( $template_id ) ) {
				return $template; // Nada escolhido, ou referência quebrada: tema normal cuida da página.
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
	 * Pré-visualização de um template — não exige uma página real
	 * associada. `?htl_preview=<id>` mostra o template usando ele mesmo
	 * como contexto (então {{post_title}} mostra o título do template);
	 * `&htl_preview_context=<post_id>` deixa ver com dado de um post
	 * real, se quiser.
	 */
	private function maybe_render_preview() {
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

		$html = get_post_meta( $template_id, HTL_Metabox::META_HTML, true );

		$html = $this->resolve_includes( $html, $css_bucket );
		$html = $this->resolve_loops( $html );
		$html = $this->replace_tags( $html, $post_id );

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
					return sprintf( '<!-- htl: loop de inclusão detectado em "%s" -->', esc_html( $slug ) );
				}

				$included = get_page_by_path( $slug, OBJECT, HTL_Post_Type::SLUG );

				if ( ! $included || 'publish' !== $included->post_status ) {
					return sprintf( '<!-- htl: template "%s" não encontrado ou não publicado -->', esc_html( $slug ) );
				}

				$included_css = get_post_meta( $included->ID, HTL_Metabox::META_CSS, true );
				if ( ! empty( $included_css ) ) {
					$css_bucket[] = $included_css;
				}

				$included_html = get_post_meta( $included->ID, HTL_Metabox::META_HTML, true );

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
	 * Expande {{loop attrs...}}...{{/loop}} numa lista real de posts do
	 * WordPress. Os atributos usam a MESMA sintaxe de um shortcode
	 * (key="value"), reaproveitando shortcode_parse_atts() do core em
	 * vez de escrever um parser próprio.
	 *
	 * Atributos aceitos: post_type, category, tag, author, s (busca),
	 * count, orderby, order.
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

				$query  = new WP_Query( $query_args );
				$output = '';

				foreach ( $query->posts as $looped_post ) {
					$output .= $this->replace_tags( $inner_template, $looped_post->ID );
				}

				if ( '' === $output ) {
					// Nenhum post encontrado — comentário visível em vez
					// de o bloco simplesmente desaparecer sem explicação.
					$output = sprintf( '<!-- htl: nenhum post encontrado para este loop (%s) -->', esc_html( $attr_string ) );
				}

				return $output;
			},
			$html
		);
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

		$tags = apply_filters(
			'htl_template_tags',
			array(
				'{{post_title}}'     => get_the_title( $post_id ),
				'{{post_content}}'   => apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ),
				'{{post_excerpt}}'   => get_the_excerpt( $post_id ),
				'{{featured_image}}' => (string) get_the_post_thumbnail_url( $post_id, 'full' ),
				'{{permalink}}'      => get_permalink( $post_id ),
				'{{site_title}}'     => get_bloginfo( 'name' ),
				'{{site_tagline}}'   => get_bloginfo( 'description' ),
				'{{current_year}}'   => date_i18n( 'Y' ),
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
