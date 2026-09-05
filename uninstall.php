<?php
/**
 * Roda SÓ quando o usuário clica em "Excluir" na tela de Plugins (não em
 * "Desativar"). Remove todo post meta criado pelo plugin E os posts do
 * custom post type "htl_template" — antes só limpávamos o meta, mas
 * agora o conteúdo em si mora em posts de verdade, então também
 * precisam ser apagados na desinstalação.
 *
 * As diretrizes de revisão do WordPress.org pedem explicitamente que
 * plugins limpem o que criaram no banco ao serem desinstalados.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$meta_keys = array( '_htl_template_html', '_htl_template_css', '_htl_template_id' );

foreach ( $meta_keys as $meta_key ) {
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- só roda uma vez, no momento da desinstalação.
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ) );
}

// Remove também os posts do tipo "htl_template" em si — não só o post
// meta que apontava pra eles.
$templates = get_posts(
	array(
		'post_type'      => 'htl_template',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $templates as $template_id ) {
	wp_delete_post( $template_id, true ); // true = ignora a lixeira, apaga de vez.
}

// Remove a option com os templates escolhidos pra home/arquivos/busca/404.
delete_option( 'htl_archive_templates' );
