<?php
/**
 * Plugin Name:       HTML Templates Lite
 * Plugin URI:        https://github.com/andermoreira/html-templates-lite
 * Description:       Crie templates HTML/CSS reusáveis e aplique em qualquer post, página ou custom post type, ignorando completamente o tema ativo para essa URL. Não depende de nenhum outro plugin nem de um tema específico.
 * Version:           0.6.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Anderson Moreira Alves
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       html-templates-lite
 *
 * ---------------------------------------------------------------------
 * Por que "Requires at least" subiu de 6.0 pra 6.4
 * ---------------------------------------------------------------------
 * A partir da v0.2.0, os templates viram posts de verdade (custom post
 * type "htl_template", ver HTL_Post_Type) com HTML/CSS guardados como
 * post meta "revisionável" via o argumento revisions_enabled do
 * register_post_meta() — recurso que só existe a partir do WordPress
 * 6.4. É o que resolve o histórico de versões (item 4 da nossa lista de
 * pendências).
 *
 * ---------------------------------------------------------------------
 * Por que este arquivo é tão curto
 * ---------------------------------------------------------------------
 * Ele só faz duas coisas: declarar o cabeçalho acima (é isso que faz o
 * WordPress listar o plugin em Plugins → Instalados) e carregar as
 * classes que fazem o trabalho de verdade. Cada classe se registra nos
 * hooks do WordPress dentro do próprio construtor — "usar o plugin" é
 * só instanciar cada uma, uma vez, quando o WP terminar de carregar os
 * plugins.
 */

defined( 'ABSPATH' ) || exit; // Bloqueia acesso direto ao arquivo via URL.

define( 'HTL_VERSION', '0.6.2' );
define( 'HTL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HTL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HTL_PLUGIN_DIR . 'includes/class-htl-post-type.php';
require_once HTL_PLUGIN_DIR . 'includes/class-htl-metabox.php';
require_once HTL_PLUGIN_DIR . 'includes/class-htl-renderer.php';
require_once HTL_PLUGIN_DIR . 'includes/class-htl-admin-list.php';
require_once HTL_PLUGIN_DIR . 'includes/class-htl-settings.php';

/**
 * plugins_loaded roda depois que TODOS os plugins já foram incluídos —
 * importante porque nossas classes dependem de funções do WordPress
 * core que só existem depois desse ponto.
 */
function htl_bootstrap() {
	// Traduções locais (languages/), mesmo domínio do cabeçalho acima.
	// Publicado no wp.org, os language packs do GlotPress carregam por
	// conta própria; esta chamada cobre o uso local do .mo incluído.
	load_plugin_textdomain( 'html-templates-lite', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	new HTL_Post_Type();
	new HTL_Metabox();
	new HTL_Renderer();
	new HTL_Admin_List();
	new HTL_Settings();
}
add_action( 'plugins_loaded', 'htl_bootstrap' );
