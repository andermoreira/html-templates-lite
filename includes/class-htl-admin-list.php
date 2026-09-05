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
			<h1><?php esc_html_e( 'HTML Templates Lite', 'html-templates-lite' ); ?></h1>
			<p><?php esc_html_e( 'The posts and pages below are using a custom HTML/CSS template instead of the theme.', 'html-templates-lite' ); ?></p>

			<?php if ( empty( $posts ) ) : ?>
				<p><?php esc_html_e( 'No page is using a template yet.', 'html-templates-lite' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'html-templates-lite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'html-templates-lite' ); ?></th>
							<th><?php esc_html_e( 'Template in use', 'html-templates-lite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'html-templates-lite' ); ?></th>
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
									<?php if ( current_user_can( 'edit_post', $template_id ) ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $template_id ) ); ?>">
											<?php echo esc_html( get_the_title( $template_id ) ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( get_the_title( $template_id ) ); ?>
									<?php endif; ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( current_user_can( 'edit_post', $post->ID ) ) : ?>
									<a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>">
										<?php esc_html_e( 'Edit', 'html-templates-lite' ); ?>
									</a>
									|
								<?php endif; ?>
								<a href="<?php echo esc_url( (string) get_permalink( $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'View', 'html-templates-lite' ); ?>
								</a>
							</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . HTL_Post_Type::SLUG ) ); ?>" class="button">
					<?php esc_html_e( 'Manage templates', 'html-templates-lite' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . HTL_Post_Type::SLUG . '&page=htl-archive-settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Home and archive settings', 'html-templates-lite' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
