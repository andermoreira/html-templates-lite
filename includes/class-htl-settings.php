<?php
/**
 * HTL_Settings
 * ---------------------------------------------------------------------
 * Tela de Ajustes onde se escolhe QUAL template usar pra home, arquivos,
 * busca e 404 — situações que não têm um post individual pra guardar
 * essa escolha (ao contrário de post/página, que usam a metabox comum).
 *
 * Guardado como uma única option (array associativo: chave da condição
 * => ID do template). HTL_Renderer::resolve_archive_template() é quem
 * lê essa option na hora de decidir o que mostrar.
 *
 * Nota sobre a home: se o site usa uma Página estática como home
 * (Ajustes → Leitura → "Uma página estática"), a home é tratada como
 * post/página normal — configure o template dela na metabox de sempre,
 * naquela Página. O ajuste "home" aqui só entra em ação quando a home
 * mostra "Seus posts mais recentes" (ou quando existe uma Página de
 * posts separada da Página inicial).
 */

defined( 'ABSPATH' ) || exit;

class HTL_Settings {

	const OPTION_KEY   = 'htl_archive_templates';
	const SETTINGS_KEY = 'htl_archive_settings_group';
	const RULES_KEY    = 'htl_singular_rules';

	/** Hook da página de Ajustes — preenchido em register_page(), usado
	 *  pra enfileirar o JS das regras só nesta tela. */
	private $page_hook = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_page() {
		// Como submenu do próprio CPT de templates, em vez de uma nova
		// entrada solta no menu — é aqui que faz sentido a pessoa
		// procurar por isso.
		$this->page_hook = add_submenu_page(
			'edit.php?post_type=' . HTL_Post_Type::SLUG,
			__( 'Settings — Home and archives', 'html-templates-lite' ),
			__( 'Settings', 'html-templates-lite' ),
			'manage_options',
			'htl-archive-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * O JS das regras (adicionar/remover linhas) só faz sentido na tela
	 * de Ajustes — nenhum asset extra nas demais telas.
	 */
	public function enqueue_assets( $hook ) {
		if ( '' === $this->page_hook || $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_script(
			'htl-settings',
			HTL_PLUGIN_URL . 'assets/settings.js',
			array(),
			HTL_VERSION,
			true
		);
	}

	public function register_setting() {
		register_setting(
			self::SETTINGS_KEY,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		register_setting(
			self::SETTINGS_KEY,
			self::RULES_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_rules' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Cada condição vira uma linha na tela — chave usada tanto na
	 * option quanto em HTL_Renderer::resolve_archive_template().
	 * Filtrável pra quem quiser adicionar mais condições (ex.: arquivo
	 * de um custom post type específico) sem editar o plugin — desde
	 * que também registre a condição correspondente via
	 * htl_archive_conditions, no renderer.
	 */
	private function get_conditions() {
		return apply_filters(
			'htl_archive_condition_labels',
			array(
				'home'     => __( 'Homepage / blog posts listing', 'html-templates-lite' ),
				'category' => __( 'Category archive (any category)', 'html-templates-lite' ),
				'tag'      => __( 'Tag archive (any tag)', 'html-templates-lite' ),
				'author'   => __( 'Author archive', 'html-templates-lite' ),
				'date'     => __( 'Date archive', 'html-templates-lite' ),
				'search'   => __( 'Search results', 'html-templates-lite' ),
				'404'      => __( '404 page (not found)', 'html-templates-lite' ),
			)
		);
	}

	/**
	 * Só aceita um ID se ele de fato apontar pra um template existente —
	 * mesmo cuidado de HTL_Metabox::save_template_picker(), pra não
	 * guardar uma referência "órfã" caso o formulário seja manipulado.
	 */
	public function sanitize( $value ) {
		$clean = array();

		if ( ! is_array( $value ) ) {
			return $clean;
		}

		foreach ( array_keys( $this->get_conditions() ) as $key ) {
			$template_id = isset( $value[ $key ] ) ? absint( $value[ $key ] ) : 0;

			if ( $template_id && HTL_Post_Type::SLUG === get_post_type( $template_id ) ) {
				$clean[ $key ] = $template_id;
			}
		}

		return $clean;
	}

	/**
	 * Regras "template por tipo de conteúdo" — só aceita linhas completas
	 * e válidas: post type existente, template publicado do tipo certo e
	 * categoria saneada (vazia = todas).
	 */
	public function sanitize_rules( $value ) {
		$clean = array();

		if ( ! is_array( $value ) ) {
			return $clean;
		}

		foreach ( $value as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$post_type   = isset( $rule['post_type'] ) ? sanitize_key( wp_unslash( $rule['post_type'] ) ) : '';
			$category    = isset( $rule['category'] ) ? sanitize_title( wp_unslash( $rule['category'] ) ) : '';
			$template_id = isset( $rule['template_id'] ) ? absint( $rule['template_id'] ) : 0;

			if ( ! $post_type || ! post_type_exists( $post_type ) ) {
				continue;
			}

			if ( ! $template_id || HTL_Post_Type::SLUG !== get_post_type( $template_id ) ) {
				continue;
			}

			$clean[] = array(
				'post_type'   => $post_type,
				'category'    => $category,
				'template_id' => $template_id,
			);
		}

		return $clean;
	}

	/**
	 * Tipos de conteúdo disponíveis pra regras — os públicos, exceto o
	 * próprio CPT de templates (uma regra que casa com templates não tem
	 * sentido: eles já só são editáveis por quem tem unfiltered_html).
	 */
	private function get_rule_post_types() {
		$types = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			if ( HTL_Post_Type::SLUG === $post_type->name ) {
				continue;
			}

			$types[ $post_type->name ] = $post_type->label;
		}

		return $types;
	}

	/**
	 * As células de UMA linha de regra. $index pode ser '__IDX__' quando
	 * a linha é o molde clonado pelo JS (assets/settings.js substitui o
	 * placeholder pelo próximo índice livre).
	 */
	private function rule_row_fields( $index, $rule, $templates ) {
		$post_type   = isset( $rule['post_type'] ) ? $rule['post_type'] : '';
		$category    = isset( $rule['category'] ) ? $rule['category'] : '';
		$template_id = isset( $rule['template_id'] ) ? (int) $rule['template_id'] : 0;

		ob_start();
		?>
		<td>
			<select name="<?php echo esc_attr( self::RULES_KEY ); ?>[<?php echo esc_attr( $index ); ?>][post_type]">
				<option value=""><?php esc_html_e( '— Choose —', 'html-templates-lite' ); ?></option>
				<?php foreach ( $this->get_rule_post_types() as $rule_pt => $rule_pt_label ) : ?>
					<option value="<?php echo esc_attr( $rule_pt ); ?>" <?php selected( $post_type, $rule_pt ); ?>><?php echo esc_html( $rule_pt_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<select name="<?php echo esc_attr( self::RULES_KEY ); ?>[<?php echo esc_attr( $index ); ?>][category]">
				<option value=""><?php esc_html_e( '— All —', 'html-templates-lite' ); ?></option>
				<?php foreach ( get_categories( array( 'hide_empty' => false ) ) as $rule_cat ) : ?>
					<option value="<?php echo esc_attr( $rule_cat->slug ); ?>" <?php selected( $category, $rule_cat->slug ); ?>><?php echo esc_html( $rule_cat->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<select name="<?php echo esc_attr( self::RULES_KEY ); ?>[<?php echo esc_attr( $index ); ?>][template_id]">
				<option value="0"><?php esc_html_e( '— None —', 'html-templates-lite' ); ?></option>
				<?php foreach ( $templates as $rule_template ) : ?>
					<option value="<?php echo esc_attr( $rule_template->ID ); ?>" <?php selected( $template_id, $rule_template->ID ); ?>><?php echo esc_html( $rule_template->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<button type="button" class="button-link htl-rule-remove" aria-label="<?php esc_attr_e( 'Remove rule', 'html-templates-lite' ); ?>"><?php esc_html_e( 'Remove', 'html-templates-lite' ); ?></button>
		</td>
		<?php
		return ob_get_clean();
	}

	public function render_page() {
		$option = get_option( self::OPTION_KEY, array() );
		$rules  = get_option( self::RULES_KEY, array() );

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

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
		<div class="wrap">
			<h1><?php esc_html_e( 'HTML Templates Lite — Home and archives', 'html-templates-lite' ); ?></h1>
			<p>
				<?php esc_html_e( 'Choose a template for each situation below. Leave it as "None" to keep the normal theme in that case.', 'html-templates-lite' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'If the homepage is a static Page (Settings → Reading), set its template directly on that Page, in the usual box — the "Homepage" setting here only applies when the home shows the latest posts.', 'html-templates-lite' ); ?>
			</p>

			<?php if ( empty( $templates ) ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: URL pra criar um novo template */
						wp_kses_post( __( 'No templates published yet. <a href="%s">Create a template</a> first.', 'html-templates-lite' ) ),
						esc_url( admin_url( 'post-new.php?post_type=' . HTL_Post_Type::SLUG ) )
					);
					?>
				</p>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_KEY ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $this->get_conditions() as $key => $label ) : ?>
						<tr>
							<th scope="row">
								<label for="htl-archive-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
							</th>
							<td>
								<select id="htl-archive-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]">
									<option value="0"><?php esc_html_e( '— None (use the theme) —', 'html-templates-lite' ); ?></option>
									<?php foreach ( $templates as $template ) : ?>
										<option value="<?php echo esc_attr( $template->ID ); ?>" <?php selected( isset( $option[ $key ] ) ? (int) $option[ $key ] : 0, $template->ID ); ?>>
											<?php echo esc_html( $template->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Posts and pages by rule', 'html-templates-lite' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Applies a template to all posts of the chosen type (or of one category) without editing page by page. The manual choice in each post\'s metabox wins over the rule; if no rule matches, the theme is used.', 'html-templates-lite' ); ?>
				</p>

				<table class="widefat striped" id="htl-rules-table" style="max-width:900px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Content type', 'html-templates-lite' ); ?></th>
							<th><?php esc_html_e( 'Category (optional)', 'html-templates-lite' ); ?></th>
							<th><?php esc_html_e( 'Template', 'html-templates-lite' ); ?></th>
							<th style="width:80px;"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'html-templates-lite' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rules as $rule_index => $rule ) : ?>
							<tr><?php echo $this->rule_row_fields( $rule_index, $rule, $templates ); // phpcs:ignore WordPress.Security.EscapeOutput -- campos escapados dentro do método. ?></tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button" id="htl-rule-add"><?php esc_html_e( 'Add rule', 'html-templates-lite' ); ?></button>
				</p>

				<script type="text/template" id="htl-rule-row-template">
					<tr><?php echo $this->rule_row_fields( '__IDX__', array(), $templates ); // phpcs:ignore WordPress.Security.EscapeOutput -- campos escapados dentro do método. ?></tr>
				</script>

				<?php submit_button(); ?>
			</form>

			<p class="description">
				<?php esc_html_e( 'Tip: inside the template, use {{loop post_type="post" count="10" paged="true"}}...{{/loop}} to list posts — in category/tag/author/search archives, the loop automatically filters by the current item, no need to specify it. The {{pagination}} tag prints the page navigation.', 'html-templates-lite' ); ?>
			</p>
		</div>
		<?php
	}
}
