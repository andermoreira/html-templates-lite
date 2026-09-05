<?php
/**
 * HTL_Post_Type
 * ---------------------------------------------------------------------
 * Registra o custom post type "htl_template" — é aqui que o HTML/CSS de
 * um template mora, e é aqui que restringimos quem pode criar/editar
 * templates (melhoria mapeada: só quem tem `unfiltered_html` deveria
 * ver a tela de criação, porque é quem realmente consegue salvar HTML
 * sem ele ser filtrado por wp_kses_post).
 */

defined( 'ABSPATH' ) || exit;

class HTL_Post_Type {

	const SLUG = 'htl_template';

	/**
	 * Todas as capabilities que o WordPress passa a gerar pra este CPT
	 * por causa de `capability_type` + `map_meta_cap => true` no
	 * register_post_type() abaixo. Qualquer uma dessas, quando checada,
	 * é redirecionada pra exigir `unfiltered_html` (ver
	 * require_unfiltered_html_for_templates()).
	 *
	 * Nota: não existe cap "create_htl_templates" — com capability_type,
	 * `create_posts` mapeia pra `edit_htl_templates`, que já está aqui.
	 */
	const TEMPLATE_CAPS = array(
		'edit_htl_template',
		'read_htl_template',
		'delete_htl_template',
		'edit_htl_templates',
		'edit_others_htl_templates',
		'publish_htl_templates',
		'read_private_htl_templates',
		'delete_htl_templates',
		'delete_private_htl_templates',
		'delete_published_htl_templates',
		'delete_others_htl_templates',
		'edit_private_htl_templates',
		'edit_published_htl_templates',
	);

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'map_meta_cap', array( $this, 'require_unfiltered_html_for_templates' ), 10, 4 );
	}

	public function register() {
		register_post_type(
			self::SLUG,
			array(
				'labels'          => array(
					'name'          => __( 'HTML Templates', 'html-templates-lite' ),
					'singular_name' => __( 'HTML Template', 'html-templates-lite' ),
					'add_new_item'  => __( 'Add New Template', 'html-templates-lite' ),
					'edit_item'     => __( 'Edit Template', 'html-templates-lite' ),
					'all_items'     => __( 'All Templates', 'html-templates-lite' ),
					'search_items'  => __( 'Search Templates', 'html-templates-lite' ),
					'not_found'     => __( 'No templates found', 'html-templates-lite' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-editor-code',
				// 'revisions' é obrigatório pro argumento revisions_enabled de
				// register_post_meta() (WP 6.4) funcionar: sem esse suporte,
				// wp_save_post_revision() retorna cedo e nenhuma revisão é
				// criada — nem do post, nem do meta de HTML/CSS.
				'supports'        => array( 'title', 'revisions' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => false,
				// A dupla abaixo é o que gera capabilities só deste CPT
				// (edit_htl_templates, edit_others_htl_templates, etc.)
				// em vez de reusar as capabilities genéricas de "post"
				// — sem isso, seria IMPOSSÍVEL distinguir "pode criar um
				// Post" de "pode criar um Template" (as duas checagens
				// cairiam na mesma string 'edit_posts'), e não teríamos
				// como restringir só um dos dois.
				'capability_type' => array( 'htl_template', 'htl_templates' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Redireciona toda capability deste CPT pra exigir `unfiltered_html`.
	 *
	 * Importante: NÃO fazemos isso lendo o array bruto de capabilities
	 * do usuário (`$user->allcaps['unfiltered_html']`) — em multisite,
	 * essa chave pode aparecer como true no papel de Administrador
	 * mesmo para quem NÃO é super admin; a restrição de verdade só é
	 * aplicada quando 'unfiltered_html' é checado como capability meta,
	 * dentro de map_meta_cap(). Por isso chamamos map_meta_cap()
	 * novamente aqui — ela mesma decide corretamente (super admin,
	 * DISALLOW_UNFILTERED_HTML, single-site, etc.), e devolve
	 * ['unfiltered_html'] ou ['do_not_allow'].
	 *
	 * Essa segunda chamada dispara este mesmo filtro de novo, mas sem
	 * recursão infinita: na segunda passada, $cap já é 'unfiltered_html'
	 * (não uma das nossas TEMPLATE_CAPS), então o `foreach` abaixo não
	 * encontra nada pra substituir e devolve o resultado sem mexer.
	 */
	public function require_unfiltered_html_for_templates( $caps, $cap, $user_id, $args ) {
		foreach ( $caps as $index => $mapped_cap ) {
			if ( in_array( $mapped_cap, self::TEMPLATE_CAPS, true ) ) {
				$resolved         = map_meta_cap( 'unfiltered_html', $user_id );
				$caps[ $index ]   = $resolved[0];
			}
		}

		return $caps;
	}
}
