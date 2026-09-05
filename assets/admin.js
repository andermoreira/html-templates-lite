/**
 * Duas responsabilidades nesta tela:
 *
 *  1. Transformar os <textarea> de HTML/CSS em editores CodeMirror
 *     (usando as configurações que o WordPress já gera via
 *     wp_enqueue_code_editor() — mesmo motor do Editor de Temas nativo).
 *
 *  2. O helper "Inserir lista de posts": monta um bloco
 *     {{loop ...}}...{{/loop}} a partir dos campos simples (tipo de
 *     conteúdo, categoria, quantidade, ordenação) e insere no editor de
 *     HTML, na posição do cursor — pra quem não quer aprender a
 *     sintaxe {{loop}} na mão.
 *
 * Se o CodeMirror não estiver disponível por algum motivo, os dois
 * recursos caem pra um fallback simples baseado em <textarea> puro —
 * progressive enhancement, nunca uma dependência obrigatória.
 */
( function () {
	var htmlEditorInstance = null;

	function initEditors() {
		if ( typeof window.wp === 'undefined' || ! window.wp.codeEditor || ! window.htlEditorSettings ) {
			return;
		}

		var htmlField = document.getElementById( 'htl_template_html' );
		var cssField = document.getElementById( 'htl_template_css' );

		if ( htmlField ) {
			htmlEditorInstance = window.wp.codeEditor.initialize( htmlField, window.htlEditorSettings.html );
		}

		if ( cssField ) {
			window.wp.codeEditor.initialize( cssField, window.htlEditorSettings.css );
		}
	}

	function fieldValue( id ) {
		var el = document.getElementById( id );
		return el ? el.value : '';
	}

	/**
	 * Monta o texto do bloco {{loop}} a partir dos campos do helper.
	 * Os atributos usam a mesma sintaxe de um shortcode do WordPress
	 * (key="value") — é isso que HTL_Renderer::resolve_loops() espera.
	 */
	function categoryFieldIsVisible() {
		var wrap = document.getElementById( 'htl-loop-category-wrap' );
		// Sem o wrap (tema/admin customizado removeu o campo) ou com
		// display diferente de 'none', consideramos visível.
		return ! wrap || wrap.style.display !== 'none';
	}

	function buildLoopSnippet() {
		var postType = fieldValue( 'htl-loop-post-type' ) || 'post';
		// Se o campo está escondido (post type sem a taxonomia
		// "category"), nunca inclui o atributo no bloco gerado — mesmo
		// que tenha sobrado um valor selecionado de antes de trocar o
		// tipo de conteúdo.
		var category = categoryFieldIsVisible() ? fieldValue( 'htl-loop-category' ) : '';
		var count = parseInt( fieldValue( 'htl-loop-count' ), 10 ) || 5;
		var orderby = fieldValue( 'htl-loop-orderby' ) || 'date';

		var attrs = 'post_type="' + postType + '" count="' + count + '" orderby="' + orderby + '"';
		if ( category ) {
			attrs += ' category="' + category + '"';
		}

		// Dentro do {{loop}}, {{post_title}}/{{post_excerpt}}/{{permalink}}
		// passam a se referir ao post daquela iteração — as mesmas tags
		// usadas fora do loop, só que com significado diferente ali dentro.
		return (
			'{{loop ' + attrs + '}}\n' +
			'  <article>\n' +
			'    <h2><a href="{{permalink}}">{{post_title}}</a></h2>\n' +
			'    <p>{{post_excerpt}}</p>\n' +
			'  </article>\n' +
			'{{/loop}}\n'
		);
	}

	function insertLoopSnippet() {
		var snippet = buildLoopSnippet();

		if ( htmlEditorInstance && htmlEditorInstance.codemirror ) {
			// replaceSelection insere na posição do cursor (ou substitui
			// o texto selecionado, se houver) — bem mais natural do que
			// sempre jogar o bloco no final do documento.
			htmlEditorInstance.codemirror.replaceSelection( snippet );
			htmlEditorInstance.codemirror.focus();
		} else {
			// Fallback: CodeMirror não carregou por algum motivo — anexa
			// no fim do textarea puro, que continua funcional.
			var field = document.getElementById( 'htl_template_html' );
			if ( field ) {
				field.value += ( field.value ? '\n' : '' ) + snippet;
			}
		}
	}

	/**
	 * Esconde o campo "Categoria" quando o post type escolhido não tem
	 * essa taxonomia (páginas, a maioria dos CPTs customizados) — em
	 * vez de deixar visível sem fazer efeito nenhum, o que fazia o loop
	 * "falhar silenciosamente" sem pista do motivo.
	 */
	function updateCategoryVisibility() {
		var select = document.getElementById( 'htl-loop-post-type' );
		var wrap = document.getElementById( 'htl-loop-category-wrap' );

		if ( ! select || ! wrap || ! window.htlEditorSettings ) {
			return;
		}

		var supported = window.htlEditorSettings.postTypesWithCategory || [];
		wrap.style.display = supported.indexOf( select.value ) !== -1 ? '' : 'none';
	}

	function initLoopHelper() {
		var button = document.getElementById( 'htl-loop-insert' );
		if ( button ) {
			button.addEventListener( 'click', insertLoopSnippet );
		}

		var postTypeSelect = document.getElementById( 'htl-loop-post-type' );
		if ( postTypeSelect ) {
			postTypeSelect.addEventListener( 'change', updateCategoryVisibility );
			updateCategoryVisibility(); // Estado inicial, já ao carregar a tela.
		}
	}

	function init() {
		initEditors();
		initLoopHelper();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
