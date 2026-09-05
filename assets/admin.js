/**
 * Duas responsabilidades nesta tela:
 *
 *  1. Transformar os <textarea> de HTML/CSS em editores CodeMirror
 *     (usando as configurações que o WordPress já gera via
 *     wp_enqueue_code_editor() — mesmo motor do Editor de Temas nativo).
 *
 *  2. Inserir no HTML, na posição do cursor: o bloco {{loop}}, tags
 *     clicáveis, {{include:slug}} e {{menu location="..."}}.
 *
 * Se o CodeMirror não estiver disponível por algum motivo, a inserção
 * cai pra um fallback simples baseado em <textarea> puro —
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

	function checkboxIsChecked( id ) {
		var el = document.getElementById( id );
		return !!( el && el.checked );
	}

	function i18nString( key ) {
		var i18n = window.htlEditorSettings && window.htlEditorSettings.i18n;
		return ( i18n && i18n[ key ] ) ? i18n[ key ] : '';
	}

	function announceInsert( success ) {
		var live = document.getElementById( 'htl-insert-status' );
		if ( ! live ) {
			return;
		}

		var message = success ? i18nString( 'inserted' ) : i18nString( 'insertFailed' );
		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = message;
		}, 20 );
	}

	/**
	 * Insere texto no editor HTML (cursor/seleção no CodeMirror, ou
	 * anexa no textarea se o editor nativo não carregou). selectToken,
	 * quando informado, vira a seleção depois da inserção — usado pra
	 * {{meta:chave}} / {{meta_url:chave}}, pra o usuário só digitar o
	 * nome do campo.
	 */
	function insertAtCursor( text, selectToken ) {
		if ( htmlEditorInstance && htmlEditorInstance.codemirror ) {
			var cm = htmlEditorInstance.codemirror;
			var from = cm.getCursor( 'from' );
			cm.replaceSelection( text );
			if ( selectToken ) {
				var tokenIndex = text.indexOf( selectToken );
				if ( tokenIndex !== -1 && text.indexOf( '\n' ) === -1 ) {
					cm.setSelection(
						{ line: from.line, ch: from.ch + tokenIndex },
						{ line: from.line, ch: from.ch + tokenIndex + selectToken.length }
					);
				}
			}
			cm.focus();
			announceInsert( true );
			return;
		}

		var field = document.getElementById( 'htl_template_html' );
		if ( ! field ) {
			announceInsert( false );
			return;
		}

		field.value += ( field.value ? '\n' : '' ) + text;
		field.focus();
		announceInsert( true );
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

	function selectedLoopOrder() {
		var select = document.getElementById( 'htl-loop-orderby' );
		if ( ! select || select.selectedIndex < 0 ) {
			return { orderby: 'date', order: 'DESC' };
		}

		var option = select.options[ select.selectedIndex ];
		return {
			orderby: option.getAttribute( 'data-orderby' ) || 'date',
			order: option.getAttribute( 'data-order' ) || ''
		};
	}

	function buildLoopSnippet() {
		var postType = fieldValue( 'htl-loop-post-type' ) || 'post';
		// Se o campo está escondido (post type sem a taxonomia
		// "category"), nunca inclui o atributo no bloco gerado — mesmo
		// que tenha sobrado um valor selecionado de antes de trocar o
		// tipo de conteúdo.
		var category = categoryFieldIsVisible() ? fieldValue( 'htl-loop-category' ) : '';
		var count = parseInt( fieldValue( 'htl-loop-count' ), 10 ) || 5;
		var sort = selectedLoopOrder();
		var paged = checkboxIsChecked( 'htl-loop-paged' );

		var attrs = 'post_type="' + postType + '" count="' + count + '" orderby="' + sort.orderby + '"';
		if ( sort.order ) {
			attrs += ' order="' + sort.order + '"';
		}
		if ( category ) {
			attrs += ' category="' + category + '"';
		}
		if ( paged ) {
			attrs += ' paged="true"';
		}

		// Dentro do {{loop}}, {{post_title}}/{{post_excerpt}}/{{permalink}}
		// passam a se referir ao post daquela iteração — as mesmas tags
		// usadas fora do loop, só que com significado diferente ali dentro.
		var block = (
			'{{loop ' + attrs + '}}\n' +
			'  <article>\n' +
			'    <h2><a href="{{permalink}}">{{post_title}}</a></h2>\n' +
			'    <p>{{post_excerpt}}</p>\n' +
			'  </article>\n' +
			'{{/loop}}\n'
		);

		// Com paginação marcada, insere também a tag que imprime a
		// navegação (links "página 2, 3...") logo abaixo da lista.
		return paged ? block + '{{pagination}}\n' : block;
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
			button.addEventListener( 'click', function () {
				insertAtCursor( buildLoopSnippet() );
			} );
		}

		var postTypeSelect = document.getElementById( 'htl-loop-post-type' );
		if ( postTypeSelect ) {
			postTypeSelect.addEventListener( 'change', updateCategoryVisibility );
			updateCategoryVisibility(); // Estado inicial, já ao carregar a tela.
		}
	}

	function initTagInsert() {
		var reference = document.querySelector( '.htl-tag-reference' );
		if ( ! reference ) {
			return;
		}

		reference.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '.htl-tag' ) : null;
			if ( ! button || ! reference.contains( button ) ) {
				return;
			}

			var text = button.getAttribute( 'data-htl-insert' );
			if ( ! text ) {
				return;
			}

			insertAtCursor( text, button.getAttribute( 'data-htl-select' ) || '' );
		} );
	}

	function initPickers() {
		var includeButton = document.getElementById( 'htl-include-insert' );
		if ( includeButton ) {
			includeButton.addEventListener( 'click', function () {
				var select = document.getElementById( 'htl-include-template' );
				if ( ! select || ! select.value ) {
					announceInsert( false );
					return;
				}
				insertAtCursor( '{{include:' + select.value + '}}' );
			} );
		}

		var menuButton = document.getElementById( 'htl-menu-insert' );
		if ( menuButton ) {
			menuButton.addEventListener( 'click', function () {
				var select = document.getElementById( 'htl-menu-location' );
				if ( ! select || ! select.value ) {
					announceInsert( false );
					return;
				}
				insertAtCursor( '{{menu location="' + select.value + '"}}' );
			} );
		}
	}

	function init() {
		initEditors();
		initLoopHelper();
		initTagInsert();
		initPickers();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
