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

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	public function register_page() {
		// Como submenu do próprio CPT de templates, em vez de uma nova
		// entrada solta no menu — é aqui que faz sentido a pessoa
		// procurar por isso.
		add_submenu_page(
			'edit.php?post_type=' . HTL_Post_Type::SLUG,
			__( 'Ajustes — Home e arquivos', 'html-templates-lite' ),
			__( 'Ajustes', 'html-templates-lite' ),
			'manage_options',
			'htl-archive-settings',
			array( $this, 'render_page' )
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
				'home'     => __( 'Página inicial / lista de posts do blog', 'html-templates-lite' ),
				'category' => __( 'Arquivo de categoria (qualquer categoria)', 'html-templates-lite' ),
				'tag'      => __( 'Arquivo de tag (qualquer tag)', 'html-templates-lite' ),
				'author'   => __( 'Arquivo de autor', 'html-templates-lite' ),
				'date'     => __( 'Arquivo de data', 'html-templates-lite' ),
				'search'   => __( 'Resultados de busca', 'html-templates-lite' ),
				'404'      => __( 'Página 404 (não encontrada)', 'html-templates-lite' ),
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

	public function render_page() {
		$option = get_option( self::OPTION_KEY, array() );

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
			<h1><?php esc_html_e( 'HTML Templates Lite — Home e arquivos', 'html-templates-lite' ); ?></h1>
			<p>
				<?php esc_html_e( 'Escolha um template pra cada situação abaixo. Deixe em "Nenhum" pra manter o tema normal nesse caso.', 'html-templates-lite' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Se a página inicial do site for uma Página estática (Ajustes → Leitura), configure o template dela direto naquela Página, na caixa de sempre — o ajuste de "Página inicial" aqui só vale quando a home mostra os posts mais recentes.', 'html-templates-lite' ); ?>
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
									<option value="0"><?php esc_html_e( '— Nenhum (usar o tema) —', 'html-templates-lite' ); ?></option>
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
				<?php submit_button(); ?>
			</form>

			<p class="description">
				<?php esc_html_e( 'Dica: dentro do template, use {{loop post_type="post" count="10"}}...{{/loop}} pra listar os posts — em arquivos de categoria/tag/autor/busca, o loop já filtra sozinho pelo item atual, sem precisar informar qual categoria/tag/autor é.', 'html-templates-lite' ); ?>
			</p>
		</div>
		<?php
	}
}
