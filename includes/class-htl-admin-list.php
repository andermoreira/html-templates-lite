<?php
/**
 * HTL_Admin_List
 * ---------------------------------------------------------------------
 * A metabox fica dentro de cada post individual — não dá pra "ver todos
 * de uma vez" sem essa tela. É uma lista de conveniência em
 * Ferramentas → Templates HTML Lite, mostrando quem usa template
 * customizado e QUAL template cada um usa (importante agora que um
 * template pode ser reusado em vários posts/páginas).
 */

defined( 'ABSPATH' ) || exit;

class HTL_Admin_List {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	public function register_page() {
		add_management_page(
			__( 'Templates HTML Lite', 'html-templates-lite' ),
			__( 'Templates HTML Lite', 'html-templates-lite' ),
			'edit_posts',
			'htl-templates',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		// meta_query com compare '>' + type NUMERIC filtra só quem tem
		// um template de verdade escolhido (id > 0) — um select deixado
		// em "Nenhum" grava 0 e não deve aparecer aqui.
		$posts = get_posts(
			array(
				'post_type'      => apply_filters( 'htl_supported_post_types', array( 'post', 'page' ) ),
				'posts_per_page' => 200,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => HTL_Metabox::META_TEMPLATE_ID,
						'value'   => '0',
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Templates HTML Lite', 'html-templates-lite' ); ?></h1>
			<p><?php esc_html_e( 'Posts e páginas abaixo estão usando um template HTML/CSS customizado em vez do tema.', 'html-templates-lite' ); ?></p>

			<?php if ( empty( $posts ) ) : ?>
				<p><?php esc_html_e( 'Nenhuma página está usando um template ainda.', 'html-templates-lite' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Título', 'html-templates-lite' ); ?></th>
							<th><?php esc_html_e( 'Tipo', 'html-templates-lite' ); ?></th>
							<th><?php esc_html_e( 'Template usado', 'html-templates-lite' ); ?></th>
							<th><?php esc_html_e( 'Ações', 'html-templates-lite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $posts as $post ) :
							$template_id = (int) get_post_meta( $post->ID, HTL_Metabox::META_TEMPLATE_ID, true );
							?>
							<tr>
								<td><?php echo esc_html( get_the_title( $post ) ); ?></td>
								<td><?php echo esc_html( $post->post_type ); ?></td>
								<td>
									<?php if ( $template_id ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $template_id ) ); ?>">
											<?php echo esc_html( get_the_title( $template_id ) ); ?>
										</a>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>">
										<?php esc_html_e( 'Editar', 'html-templates-lite' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( (string) get_permalink( $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Ver', 'html-templates-lite' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . HTL_Post_Type::SLUG ) ); ?>" class="button">
					<?php esc_html_e( 'Gerenciar templates', 'html-templates-lite' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . HTL_Post_Type::SLUG . '&page=htl-archive-settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Ajustes de home e arquivos', 'html-templates-lite' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
