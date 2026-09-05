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
		'create_htl_templates',
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
					'name'          => __( 'Templates HTML', 'html-templates-lite' ),
					'singular_name' => __( 'Template HTML', 'html-templates-lite' ),
					'add_new_item'  => __( 'Adicionar novo template', 'html-templates-lite' ),
					'edit_item'     => __( 'Editar template', 'html-templates-lite' ),
					'all_items'     => __( 'Todos os templates', 'html-templates-lite' ),
					'search_items'  => __( 'Buscar templates', 'html-templates-lite' ),
					'not_found'     => __( 'Nenhum template encontrado', 'html-templates-lite' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-editor-code',
				'supports'        => array( 'title' ),
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
