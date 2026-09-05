<?php
/**
 * Plugin Name:       HTML Templates Lite — Test Kit Loader
 * Description:       One-shot seeder for the manual test kit. Development only — delete after testing. Do not ship in the plugin zip.
 * Version:           0.6.3
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Anderson Moreira Alves
 * License:           GPL-2.0-or-later
 * Text Domain:       html-templates-lite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'htl_kit_register_menu_location', 20 );
add_action( 'admin_menu', 'htl_kit_register_page' );
add_action( 'admin_init', 'htl_kit_handle_load' );
add_action( 'admin_notices', 'htl_kit_admin_notice' );

/**
 * Block themes often have no classic location named "primary".
 * Register one while this loader stays active so {{menu location="primary"}} can resolve.
 */
function htl_kit_register_menu_location() {
	register_nav_menu( 'primary', 'Primary (HTL test kit)' );
}

function htl_kit_register_page() {
	add_management_page(
		'HTL Test Kit',
		'HTL Test Kit',
		'manage_options',
		'htl-test-kit',
		'htl_kit_render_page'
	);
}

function htl_kit_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$plugin_ok = class_exists( 'HTL_Post_Type' ) && class_exists( 'HTL_Metabox' ) && class_exists( 'HTL_Settings' );
	?>
	<div class="wrap">
		<h1>HTML Templates Lite — kit de teste</h1>
		<?php if ( ! $plugin_ok ) : ?>
			<div class="notice notice-error"><p>Ative o plugin <strong>HTML Templates Lite</strong> antes de carregar o kit.</p></div>
		<?php else : ?>
			<p>Cria os 4 templates, copia os assets, gera posts de teste, menu, regras de exibição e Ajustes de arquivo. Pode rodar de novo: atualiza o que já existe (slugs fixos).</p>
			<p>Depois de validar, apague este plugin. Ele não faz parte do produto.</p>
			<form method="post">
				<?php wp_nonce_field( 'htl_kit_load' ); ?>
				<input type="hidden" name="htl_kit_action" value="load">
				<?php submit_button( 'Carregar kit de teste', 'primary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

function htl_kit_handle_load() {
	if ( ! isset( $_POST['htl_kit_action'] ) || 'load' !== wp_unslash( $_POST['htl_kit_action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'unfiltered_html' ) ) {
		wp_die( esc_html( 'Você precisa ser administrador (unfiltered_html) para carregar o kit.' ) );
	}

	check_admin_referer( 'htl_kit_load' );

	if ( ! class_exists( 'HTL_Post_Type' ) || ! class_exists( 'HTL_Metabox' ) || ! class_exists( 'HTL_Settings' ) ) {
		set_transient( 'htl_kit_notice', array( 'error', 'Ative o HTML Templates Lite primeiro.' ), 60 );
		return;
	}

	$result = htl_kit_load();
	set_transient( 'htl_kit_notice', $result, 120 );
	wp_safe_redirect( admin_url( 'tools.php?page=htl-test-kit' ) );
	exit;
}

function htl_kit_admin_notice() {
	$notice = get_transient( 'htl_kit_notice' );
	if ( ! is_array( $notice ) || 2 !== count( $notice ) ) {
		return;
	}

	delete_transient( 'htl_kit_notice' );
	$class = 'error' === $notice[0] ? 'notice-error' : 'notice-success';
	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . wp_kses_post( $notice[1] ) . '</p></div>';
}

/**
 * @return array{0:string,1:string} [status, html message]
 */
function htl_kit_load() {
	$base = plugin_dir_path( __FILE__ );

	$templates = array(
		array( 'Kit Header', 'kit-header', 'templates/1-kit-header.html' ),
		array( 'Kit Footer', 'kit-footer', 'templates/2-kit-footer.html' ),
		array( 'Kit Post', 'kit-post', 'templates/3-kit-post.html' ),
		array( 'Kit Archive', 'kit-archive', 'templates/4-kit-archive.html' ),
	);

	$ids = array();
	foreach ( $templates as $row ) {
		$html_path = $base . $row[2];
		if ( ! is_readable( $html_path ) ) {
			return array( 'error', 'Arquivo ausente no zip: <code>' . esc_html( $row[2] ) . '</code>.' );
		}

		$ids[ $row[1] ] = htl_kit_upsert_template( $row[0], $row[1], (string) file_get_contents( $html_path ) );
	}

	htl_kit_copy_assets( $base . 'uploads/htl-templates' );
	htl_kit_prefer_classic_theme();
	update_option( 'posts_per_page', 2 );
	update_option( 'show_on_front', 'posts' );

	$destaques = htl_kit_ensure_category( 'Destaques', 'destaques' );
	$posts     = htl_kit_ensure_posts( $destaques, (int) $ids['kit-post'] );
	htl_kit_ensure_menu( $posts );
	htl_kit_apply_settings( (int) $ids['kit-post'], (int) $ids['kit-archive'] );

	$home = esc_url( home_url( '/' ) );
	$cat  = esc_url( get_category_link( $destaques ) );
	$one  = esc_url( get_permalink( $posts['fonte'] ) );

	$message  = 'Kit carregado. Abra: ';
	$message .= '<a href="' . $home . '" target="_blank" rel="noopener">home</a>, ';
	$message .= '<a href="' . $cat . '" target="_blank" rel="noopener">categoria Destaques</a>, ';
	$message .= '<a href="' . $one . '" target="_blank" rel="noopener">post com campo fonte</a>. ';
	$message .= 'Header escuro + barra laranja no singular + cabeçalho azul no arquivo = assets OK. ';
	$message .= 'Checklist: <code>test-kit/README.md</code> seção 5. Depois apague este plugin.';

	return array( 'success', $message );
}

function htl_kit_upsert_template( $title, $slug, $html ) {
	$existing = get_page_by_path( $slug, OBJECT, HTL_Post_Type::SLUG );
	$post_id  = ( $existing && isset( $existing->ID ) ) ? (int) $existing->ID : 0;

	if ( $post_id ) {
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);
	} else {
		$post_id = wp_insert_post(
			array(
				'post_type'   => HTL_Post_Type::SLUG,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_die( esc_html( $post_id->get_error_message() ) );
		}
	}

	update_post_meta( $post_id, HTL_Metabox::META_HTML, $html );
	update_post_meta( $post_id, HTL_Metabox::META_CSS, '' );

	$upload_dir = wp_upload_dir();
	wp_mkdir_p( trailingslashit( $upload_dir['basedir'] ) . 'htl-templates/' . $slug );

	return (int) $post_id;
}

function htl_kit_copy_assets( $src ) {
	if ( ! is_dir( $src ) ) {
		return;
	}

	$upload_dir = wp_upload_dir();
	$dest       = trailingslashit( $upload_dir['basedir'] ) . 'htl-templates';
	htl_kit_copy_dir( $src, $dest );
}

function htl_kit_copy_dir( $src, $dest ) {
	wp_mkdir_p( $dest );
	$handle = opendir( $src );
	if ( false === $handle ) {
		return;
	}

	while ( false !== ( $file = readdir( $handle ) ) ) {
		if ( '.' === $file || '..' === $file ) {
			continue;
		}

		$from = $src . '/' . $file;
		$to   = $dest . '/' . $file;

		if ( is_dir( $from ) ) {
			htl_kit_copy_dir( $from, $to );
		} else {
			copy( $from, $to );
		}
	}

	closedir( $handle );
}

function htl_kit_prefer_classic_theme() {
	$candidates = array( 'twentytwentyone', 'twentytwenty', 'twentynineteen' );
	$current    = get_stylesheet();

	if ( in_array( $current, $candidates, true ) ) {
		return;
	}

	foreach ( $candidates as $stylesheet ) {
		if ( wp_get_theme( $stylesheet )->exists() ) {
			switch_theme( $stylesheet );
			return;
		}
	}
}

function htl_kit_ensure_category( $name, $slug ) {
	$term = get_term_by( 'slug', $slug, 'category' );
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}

	$created = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	if ( is_wp_error( $created ) ) {
		wp_die( esc_html( $created->get_error_message() ) );
	}

	return (int) $created['term_id'];
}

/**
 * @return array<string,int>
 */
function htl_kit_ensure_posts( $destaques_id, $kit_post_id ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$specs = array(
		'alpha'  => array(
			'title'      => 'Kit Alpha — imagem destacada',
			'content'    => 'Post com imagem destacada. Sem categoria Destaques: deve usar o tema quando não houver escolha manual.',
			'destaques'  => false,
			'tags'       => array(),
			'password'   => '',
			'fonte'      => '',
			'comments'   => false,
			'featured'   => true,
			'template'   => 0,
		),
		'beta'   => array(
			'title'      => 'Kit Beta — Destaques (regra)',
			'content'    => 'Categoria Destaques, sem metabox manual: a regra deve aplicar Kit Post.',
			'destaques'  => true,
			'tags'       => array(),
			'password'   => '',
			'fonte'      => '',
			'comments'   => false,
			'featured'   => false,
			'template'   => 0,
		),
		'gamma'  => array(
			'title'      => 'Kit Gamma — Destaques e tags',
			'content'    => 'Destaques + tag teste. Serve o loop, a regra e as tags do post.',
			'destaques'  => true,
			'tags'       => array( 'teste' ),
			'password'   => '',
			'fonte'      => '',
			'comments'   => false,
			'featured'   => false,
			'template'   => 0,
		),
		'delta'  => array(
			'title'      => 'Kit Delta — protegido por senha',
			'content'    => 'Conteúdo secreto do kit. Visitante anônimo não deve ver este texto.',
			'destaques'  => false,
			'tags'       => array(),
			'password'   => 'kit',
			'fonte'      => '',
			'comments'   => false,
			'featured'   => false,
			'template'   => 0,
		),
		'fonte'  => array(
			'title'      => 'Kit Epsilon — campo fonte',
			'content'    => 'Post com campo personalizado fonte e comentários. Metabox manual = Kit Post.',
			'destaques'  => false,
			'tags'       => array(),
			'password'   => '',
			'fonte'      => 'Kit de teste 0.6.0',
			'comments'   => true,
			'featured'   => false,
			'template'   => $kit_post_id,
		),
	);

	$ids = array();
	foreach ( $specs as $key => $spec ) {
		$slug    = 'kit-' . $key;
		$existing = get_page_by_path( $slug, OBJECT, 'post' );
		$post_id  = ( $existing && isset( $existing->ID ) ) ? (int) $existing->ID : 0;

		$payload = array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => $spec['title'],
			'post_name'     => $slug,
			'post_content'   => $spec['content'],
			'post_password'  => $spec['password'],
			'comment_status' => $spec['comments'] ? 'open' : 'closed',
		);

		if ( $post_id ) {
			$payload['ID'] = $post_id;
			wp_update_post( $payload );
		} else {
			$post_id = wp_insert_post( $payload, true );
			if ( is_wp_error( $post_id ) ) {
				wp_die( esc_html( $post_id->get_error_message() ) );
			}
		}

		$cats = array( (int) get_option( 'default_category' ) );
		if ( $spec['destaques'] ) {
			$cats[] = $destaques_id;
		}
		wp_set_post_categories( $post_id, $cats );
		wp_set_post_tags( $post_id, $spec['tags'], false );

		if ( '' !== $spec['fonte'] ) {
			update_post_meta( $post_id, 'fonte', $spec['fonte'] );
		}

		update_post_meta( $post_id, HTL_Metabox::META_TEMPLATE_ID, (int) $spec['template'] );

		if ( $spec['featured'] && ! has_post_thumbnail( $post_id ) ) {
			htl_kit_attach_featured_image( $post_id );
		}

		if ( $spec['comments'] && 0 === (int) get_comments( array( 'post_id' => $post_id, 'count' => true ) ) ) {
			wp_insert_comment(
				array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => 'Kit Tester',
					'comment_author_email' => 'kit@example.com',
					'comment_content'      => 'Comentário aprovado do kit de teste.',
					'comment_approved'     => 1,
				)
			);
		}

		$ids[ $key ] = (int) $post_id;
	}

	return $ids;
}

function htl_kit_attach_featured_image( $post_id ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return;
	}

	$tmp = wp_tempnam( 'htl-kit-featured' );
	$png = $tmp . '.png';
	$im  = imagecreatetruecolor( 800, 420 );
	$bg  = imagecolorallocate( $im, 224, 92, 62 );
	$fg  = imagecolorallocate( $im, 255, 255, 255 );
	imagefilledrectangle( $im, 0, 0, 800, 420, $bg );
	imagestring( $im, 5, 300, 200, 'HTL test kit', $fg );
	imagepng( $im, $png );
	imagedestroy( $im );
	if ( is_readable( $tmp ) ) {
		unlink( $tmp );
	}

	$attachment_id = media_handle_sideload(
		array(
			'name'     => 'kit-featured.png',
			'tmp_name' => $png,
		),
		$post_id
	);

	if ( ! is_wp_error( $attachment_id ) ) {
		set_post_thumbnail( $post_id, $attachment_id );
	}
}

function htl_kit_ensure_menu( $posts ) {
	$menu_name = 'Kit Menu';
	$menu      = wp_get_nav_menu_object( $menu_name );
	$menu_id   = ( $menu && isset( $menu->term_id ) ) ? (int) $menu->term_id : 0;

	if ( ! $menu_id ) {
		$created = wp_create_nav_menu( $menu_name );
		if ( is_wp_error( $created ) ) {
			return;
		}
		$menu_id = (int) $created;
	}

	$existing_items = wp_get_nav_menu_items( $menu_id );
	if ( empty( $existing_items ) ) {
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => 'Home',
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
			)
		);

		foreach ( array( 'alpha', 'beta', 'fonte' ) as $key ) {
			if ( empty( $posts[ $key ] ) ) {
				continue;
			}
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => get_the_title( $posts[ $key ] ),
					'menu-item-object'    => 'post',
					'menu-item-object-id' => $posts[ $key ],
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
			);
		}
	}

	$locations            = get_theme_mod( 'nav_menu_locations' );
	$locations            = is_array( $locations ) ? $locations : array();
	$locations['primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}

function htl_kit_apply_settings( $kit_post_id, $kit_archive_id ) {
	update_option(
		HTL_Settings::OPTION_KEY,
		array(
			'home'     => $kit_archive_id,
			'category' => $kit_archive_id,
			'search'   => $kit_archive_id,
		)
	);

	update_option(
		HTL_Settings::RULES_KEY,
		array(
			array(
				'post_type'   => 'post',
				'category'    => 'destaques',
				'template_id' => $kit_post_id,
			),
		)
	);
}
