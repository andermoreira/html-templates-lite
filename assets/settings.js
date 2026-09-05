/**
 * Tela "Ajustes — Home e arquivos": adiciona/remove linhas na tabela de
 * regras (template por tipo de conteúdo). A linha-molde vive num
 * <script type="text/template"> com o índice __IDX__ nos nomes dos
 * campos; ao adicionar uma linha, o placeholder é substituído pelo
 * próximo índice livre, então o PHP (sanitize_rules) recebe um array
 * limpo no POST — sem jQuery nem dependências além do DOM.
 */
( function () {
	var RULES_NAME_PATTERN = /htl_singular_rules\[(\d+)\]/g;
	var INDEX_PLACEHOLDER = /__IDX__/g;

	function init() {
		var table = document.getElementById( 'htl-rules-table' );
		var addButton = document.getElementById( 'htl-rule-add' );
		var template = document.getElementById( 'htl-rule-row-template' );

		if ( ! table || ! addButton || ! template ) {
			return;
		}

		var tbody = table.tBodies[ 0 ];

		// Próximo índice livre = maior índice já presente no DOM + 1.
		// Ignora buracos causados por linhas removidas — tanto faz para
		// o sanitize do lado do PHP.
		function nextIndex() {
			var max = -1;
			var match;

			RULES_NAME_PATTERN.lastIndex = 0;
			while ( ( match = RULES_NAME_PATTERN.exec( tbody.innerHTML ) ) !== null ) {
				max = Math.max( max, parseInt( match[ 1 ], 10 ) );
			}

			return max + 1;
		}

		addButton.addEventListener( 'click', function () {
			var row = template.textContent.trim().replace( INDEX_PLACEHOLDER, nextIndex() );
			tbody.insertAdjacentHTML( 'beforeend', row );
		} );

		table.addEventListener( 'click', function ( event ) {
			var removeButton = event.target.closest( '.htl-rule-remove' );
			if ( removeButton ) {
				removeButton.closest( 'tr' ).remove();
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
