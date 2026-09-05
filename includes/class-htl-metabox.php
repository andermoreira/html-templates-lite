<?php
/**
 * HTL_Metabox
 * ---------------------------------------------------------------------
 * Duas telas diferentes, uma classe só:
 *
 *   1. Em post/página (e outros post types filtrados via
 *      htl_supported_post_types): um <select> simples — "qual template
 *      usar aqui?". Nenhum HTML/CSS é digitado nesta tela.
 *
 *   2. No custom post type "htl_template" (registrado por
 *      HTL_Post_Type): os campos de HTML e CSS de verdade, com editor
 *      de código nativo do WP.
 *
 * Essa separação é o que resolve a reusabilidade (item 5): o mesmo
 * template pode ser escolhido em N posts/páginas diferentes, porque o
 * conteúdo mora num lugar só.
 */

defined( 'ABSPATH' ) || exit;

class HTL_Metabox {

	// Chaves de post meta centralizadas aqui — evita strings mágicas
	// espalhadas pelas outras classes (elas leem estas constantes).
	const META_HTML        = '_htl_template_html'; // Vive nos posts "htl_template".
	const META_CSS          = '_htl_template_css';  // Vive nos posts "htl_template".
	const META_TEMPLATE_ID  = '_htl_template_id';   // Vive nos posts/páginas normais — aponta pro template escolhido.
	const NONCE_ACTION      = 'htl_save_template';
	const NONCE_FIELD       = 'htl_nonce';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
		add_action( 'admin_post_htl_duplicate_template', array( $this, 'handle_duplicate' ) );
	}

	/**
	 * Em quais post types aparece o SELETOR de template (não o editor de
	 * HTML/CSS — esse é fixo no CPT htl_template). Filtro, de propósito:
	 * quem instalar o plugin pode adicionar um CPT próprio via
	 * functions.php, sem precisar editar o plugin.
	 *
	 *     add_filter( 'htl_supported_post_types', function ( $types ) {
	 *         $types[] = 'produto';
	 *         return $types;
	 *     } );
	 */
	private function get_supported_post_types() {
		return apply_filters( 'htl_supported_post_types', array( 'post', 'page' ) );
	}

	/**
	 * Registra os três meta keys do plugin no sistema de meta do
	 * WordPress. O motivo de fazer isso (em vez de só usar
	 * update_post_meta/get_post_meta direto, como na v0.1) é o argumento
	 * `revisions_enabled` — introduzido no WordPress 6.4 (ver
	 * wp_post_revision_meta_keys()). Com ele, o HTML/CSS de um template
	 * passa a ganhar uma revisão a cada salvamento, igual ao conteúdo de
	 * qualquer post — e "Restaurar esta revisão" passa a funcionar
	 * também pra esses campos. Isso resolve o item 4 da nossa lista de
	 * pendências.
	 *
	 * Em WordPress mais antigo que 6.4, este argumento é simplesmente
	 * ignorado pelo core — o plugin continua funcionando normalmente,
	 * só sem o histórico de revisão dos campos.
	 */
	public function register_meta_fields() {
		$common_args = array(
			'single'            => true,
			'show_in_rest'      => false,
			'revisions_enabled' => true,
		);

		register_post_meta( '', self::META_HTML, array_merge( $common_args, array( 'type' => 'string' ) ) );
		register_post_meta( '', self::META_CSS, array_merge( $common_args, array( 'type' => 'string' ) ) );
		register_post_meta( '', self::META_TEMPLATE_ID, array_merge( $common_args, array( 'type' => 'integer' ) ) );
	}

	public function register_metabox() {
		// Metabox 1: o seletor, em post/página e no que mais for filtrado.
		foreach ( $this->get_supported_post_types() as $post_type ) {
			add_meta_box(
				'htl_template_picker',
				__( 'Template HTML/CSS (HTML Templates Lite)', 'html-templates-lite' ),
				array( $this, 'render_picker' ),
				$post_type,
				'normal',
				'high'
			);
		}

		// Metabox 2: o editor de HTML/CSS de verdade, só no CPT de template.
		add_meta_box(
			'htl_template_editor',
			__( 'Conteúdo do template (HTML Templates Lite)', 'html-templates-lite' ),
			array( $this, 'render_editor' ),
			HTL_Post_Type::SLUG,
			'normal',
			'high'
		);
	}

	/**
	 * Carrega o editor de código NATIVO do WordPress (CodeMirror, já
	 * embutido no core desde a 4.9 — o mesmo motor do Editor de Temas em
	 * Aparência → Editor de Temas). Só faz sentido na tela do próprio
	 * template — é lá que existe HTML/CSS pra editar; nas telas de
	 * post/página normais agora só tem um <select>, sem asset extra
	 * nenhum precisando ser carregado.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		global $post;
		if ( ! $post || HTL_Post_Type::SLUG !== $post->post_type ) {
			return;
		}

		$html_settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
		$css_settings  = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

		wp_enqueue_script(
			'htl-admin',
			HTL_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery', 'code-editor' ),
			HTL_VERSION,
			true
		);

		// Nem todo post type usa a taxonomia "category" (páginas nunca
		// usam; muitos CPTs customizados também não). O JS usa esta
		// lista pra esconder o campo "Categoria" no helper de loop
		// quando o tipo escolhido não tiver essa taxonomia — em vez de
		// deixar o campo visível sem fazer efeito nenhum (melhoria
		// mapeada: evitar o loop "falhar silenciosamente" nesse caso).
		$post_types_with_category = array();
		foreach ( get_post_types( array( 'public' => true ) ) as $public_post_type ) {
			if ( is_object_in_taxonomy( $public_post_type, 'category' ) ) {
				$post_types_with_category[] = $public_post_type;
			}
		}

		wp_localize_script(
			'htl-admin',
			'htlEditorSettings',
			array(
				'html'                   => $html_settings,
				'css'                    => $css_settings,
				'postTypesWithCategory'  => $post_types_with_category,
			)
		);

		wp_enqueue_style( 'htl-admin', HTL_PLUGIN_URL . 'assets/admin.css', array(), HTL_VERSION );
	}

	/**
	 * Metabox 1 — aparece em post/página: só um <select> com os
	 * templates publicados. Escolher "Nenhum" (valor 0) desativa o
	 * plugin pra aquela página e devolve o controle pro tema normal.
	 */
	public function render_picker( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$selected_id = (int) get_post_meta( $post->ID, self::META_TEMPLATE_ID, true );

		$templates = get_posts(
			array(
				'post_type'      => HTL_Post_Type::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p>
			<label for="htl_template_id"><strong><?php esc_html_e( 'Template a usar no lugar do tema', 'html-templates-lite' ); ?></strong></label><br />
			<select id="htl_template_id" name="htl_template_id" style="max-width:100%;width:100%;">
				<option value="0"><?php esc_html_e( '— Nenhum (usar o tema normalmente) —', 'html-templates-lite' ); ?></option>
				<?php foreach ( $templates as $template ) : ?>
					<option value="<?php echo esc_attr( $template->ID ); ?>" <?php selected( $selected_id, $template->ID ); ?>>
						<?php echo esc_html( $template->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<?php if ( empty( $templates ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: URL pra criar um novo template */
					wp_kses_post( __( 'Nenhum template publicado ainda. <a href="%s">Crie um template</a> primeiro.', 'html-templates-lite' ) ),
					esc_url( admin_url( 'post-new.php?post_type=' . HTL_Post_Type::SLUG ) )
				);
				?>
			</p>
		<?php endif; ?>

		<p class="description">
			<?php esc_html_e( 'O mesmo template pode ser escolhido em vários posts/páginas — edite o conteúdo em Templates HTML → Todos os templates.', 'html-templates-lite' ); ?>
		</p>
		<?php
	}

	/**
	 * Metabox 2 — aparece só no CPT htl_template: os campos de HTML e
	 * CSS de verdade, com o mesmo aviso de sanitização condicional da
	 * v0.1.
	 */
	public function render_editor( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$html = get_post_meta( $post->ID, self::META_HTML, true );
		$css  = get_post_meta( $post->ID, self::META_CSS, true );

		$can_use_raw_html = current_user_can( 'unfiltered_html' );
		?>
		<?php if ( 'auto-draft' !== $post->post_status ) : ?>
			<p>
				<a
					href="<?php echo esc_url( add_query_arg( 'htl_preview', $post->ID, home_url( '/' ) ) ); ?>"
					class="button"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php esc_html_e( 'Pré-visualizar template', 'html-templates-lite' ); ?>
				</a>
				<a
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htl_duplicate_template&template_id=' . $post->ID ), 'htl_duplicate_' . $post->ID ) ); ?>"
					class="button"
				>
					<?php esc_html_e( 'Salvar como cópia', 'html-templates-lite' ); ?>
				</a>
				<br />
				<span class="description">
					<?php esc_html_e( 'A pré-visualização mostra a última versão SALVA — mudanças ainda não salvas no editor abaixo não aparecem nela.', 'html-templates-lite' ); ?>
				</span>
			</p>
		<?php endif; ?>
		<div class="htl-loop-helper" style="background:#f6f7f7;border:1px solid #dcdcde;padding:12px;margin-bottom:12px;">
			<p style="margin-top:0;"><strong><?php esc_html_e( 'Inserir lista de posts (sem escrever código)', 'html-templates-lite' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'Monta um bloco {{loop}}...{{/loop}} pronto e insere no HTML abaixo, na posição onde o cursor estiver.', 'html-templates-lite' ); ?></p>
			<p>
				<label><?php esc_html_e( 'Tipo de conteúdo', 'html-templates-lite' ); ?><br />
					<select id="htl-loop-post-type">
						<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $htl_pt ) : ?>
							<option value="<?php echo esc_attr( $htl_pt->name ); ?>"><?php echo esc_html( $htl_pt->label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				&nbsp;&nbsp;
				<label id="htl-loop-category-wrap"><?php esc_html_e( 'Categoria (opcional)', 'html-templates-lite' ); ?><br />
					<select id="htl-loop-category">
						<option value=""><?php esc_html_e( '— Todas —', 'html-templates-lite' ); ?></option>
						<?php foreach ( get_categories( array( 'hide_empty' => false ) ) as $htl_cat ) : ?>
							<option value="<?php echo esc_attr( $htl_cat->slug ); ?>"><?php echo esc_html( $htl_cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				&nbsp;&nbsp;
				<label><?php esc_html_e( 'Quantidade', 'html-templates-lite' ); ?><br />
					<input type="number" id="htl-loop-count" value="5" min="1" max="50" style="width:70px;" />
				</label>
				&nbsp;&nbsp;
				<label><?php esc_html_e( 'Ordenar por', 'html-templates-lite' ); ?><br />
					<select id="htl-loop-orderby">
						<option value="date"><?php esc_html_e( 'Data (mais recente)', 'html-templates-lite' ); ?></option>
						<option value="title"><?php esc_html_e( 'Título', 'html-templates-lite' ); ?></option>
						<option value="rand"><?php esc_html_e( 'Aleatório', 'html-templates-lite' ); ?></option>
					</select>
				</label>
			</p>
			<p style="margin-bottom:0;">
				<button type="button" id="htl-loop-insert" class="button"><?php esc_html_e( 'Inserir bloco de posts no HTML', 'html-templates-lite' ); ?></button>
			</p>
		</div>

		<?php if ( ! $can_use_raw_html ) : ?>
			<p class="description">
				<?php esc_html_e( 'Seu usuário não tem a permissão "unfiltered_html" — o HTML salvo será filtrado por segurança (tags e atributos potencialmente perigosos são removidos automaticamente).', 'html-templates-lite' ); ?>
			</p>
		<?php endif; ?>

		<p>
			<label for="htl_template_html"><strong><?php esc_html_e( 'HTML', 'html-templates-lite' ); ?></strong></label><br />
			<textarea id="htl_template_html" name="htl_template_html" rows="14" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $html ); ?></textarea>
		</p>

		<p>
			<label for="htl_template_css"><strong><?php esc_html_e( 'CSS', 'html-templates-lite' ); ?></strong></label><br />
			<textarea id="htl_template_css" name="htl_template_css" rows="10" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $css ); ?></textarea>
		</p>

		<p class="description">
			<?php
			printf(
				/* translators: %s: lista de tags dinâmicas disponíveis, cada uma dentro de <code> */
				esc_html__( 'Tags dinâmicas disponíveis: %s — adicione mais via o filtro htl_template_tags.', 'html-templates-lite' ),
				'<code>{{post_title}}</code>, <code>{{post_content}}</code>, <code>{{post_excerpt}}</code>, <code>{{featured_image}}</code>, <code>{{permalink}}</code>, <code>{{site_title}}</code>, <code>{{site_tagline}}</code>, <code>{{current_year}}</code>'
			);
			?>
			<br />
			<?php
			printf(
				/* translators: %s: exemplo de tag de inclusão, dentro de <code> */
				esc_html__( 'Inclua outro template dentro deste com %s, usando o slug (nome amigável na URL) do template incluído.', 'html-templates-lite' ),
				'<code>{{include:slug-do-template}}</code>'
			);
			?>
			<br />
			<?php esc_html_e( 'Prefere não escrever a sintaxe do loop na mão? Use o formulário acima, em "Inserir lista de posts" — ele monta o bloco {{loop}}...{{/loop}} pra você.', 'html-templates-lite' ); ?>
		</p>
		<?php
	}

	/**
	 * Um único hook de save_post, com as três checagens padrão de
	 * segurança que todo save handler do WP deveria ter — e então
	 * delega pro método certo dependendo de QUAL das duas telas foi
	 * salva.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_FIELD ] ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( HTL_Post_Type::SLUG === get_post_type( $post_id ) ) {
			$this->save_template_content( $post_id );
		} else {
			$this->save_template_picker( $post_id );
		}
	}

	/**
	 * Salva qual template foi escolhido num post/página comum.
	 */
	private function save_template_picker( $post_id ) {
		$template_id = isset( $_POST['htl_template_id'] ) ? absint( $_POST['htl_template_id'] ) : 0;

		// Só aceita o valor se ele de fato apontar pra um post do tipo
		// certo — protege contra um ID "órfão" caso alguém manipule o
		// POST manualmente (o <select> normal nunca mandaria um ID
		// inválido, mas nunca confiamos só na interface).
		if ( $template_id > 0 && HTL_Post_Type::SLUG !== get_post_type( $template_id ) ) {
			$template_id = 0;
		}

		update_post_meta( $post_id, self::META_TEMPLATE_ID, $template_id );
	}

	/**
	 * Salva o HTML/CSS de um template (post do tipo htl_template).
	 */
	private function save_template_content( $post_id ) {
		if ( isset( $_POST['htl_template_html'] ) ) {
			$raw_html = wp_unslash( $_POST['htl_template_html'] );

			// Só quem tem a capability unfiltered_html (administradores,
			// por padrão, em instalação single-site) pode salvar HTML
			// 100% livre. Qualquer outro papel tem o conteúdo passado
			// pelo wp_kses_post — a MESMA sanitização que o WordPress já
			// aplica ao conteúdo normal de um post.
			$sanitized_html = current_user_can( 'unfiltered_html' )
				? $raw_html
				: wp_kses_post( $raw_html );

			update_post_meta( $post_id, self::META_HTML, $sanitized_html );
		}

		if ( isset( $_POST['htl_template_css'] ) ) {
			$raw_css = wp_unslash( $_POST['htl_template_css'] );

			// wp_kses é feito pra HTML, não pra CSS — aqui só garantimos
			// que ninguém consiga fechar a tag <style> antecipadamente e
			// injetar HTML/script logo em seguida.
			$safe_css = str_replace( '</style', '', $raw_css );

			update_post_meta( $post_id, self::META_CSS, $safe_css );
		}
	}

	/**
	 * Handler do botão "Salvar como cópia" — cria um novo template
	 * (rascunho) com o mesmo HTML/CSS do original, útil pra partir de
	 * um template parecido em vez de começar do zero.
	 */
	public function handle_duplicate() {
		$template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0;

		check_admin_referer( 'htl_duplicate_' . $template_id );

		if ( ! $template_id || HTL_Post_Type::SLUG !== get_post_type( $template_id ) ) {
			wp_die( esc_html__( 'Template inválido.', 'html-templates-lite' ) );
		}

		if ( ! current_user_can( 'edit_post', $template_id ) ) {
			wp_die( esc_html__( 'Você não tem permissão para duplicar este template.', 'html-templates-lite' ) );
		}

		$original = get_post( $template_id );

		$new_id = wp_insert_post(
			array(
				'post_type'   => HTL_Post_Type::SLUG,
				'post_status' => 'draft',
				/* translators: %s: título do template original */
				'post_title'  => sprintf( __( '%s (cópia)', 'html-templates-lite' ), $original->post_title ),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		// Copia o HTML/CSS direto do original — quem chegou até aqui já
		// comprovou ter permissão de editá-lo (checagem acima), então
		// não precisa passar de novo pela sanitização condicional de
		// save_template_content(); é o mesmo conteúdo, só num post novo.
		update_post_meta( $new_id, self::META_HTML, get_post_meta( $template_id, self::META_HTML, true ) );
		update_post_meta( $new_id, self::META_CSS, get_post_meta( $template_id, self::META_CSS, true ) );

		wp_safe_redirect( get_edit_post_link( $new_id, 'raw' ) );
		exit;
	}
}
