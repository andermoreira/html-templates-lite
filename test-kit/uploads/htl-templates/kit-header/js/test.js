/**
 * Kit de teste — JS GLOBAL (do include kit-header).
 * Validação: 1) aparece no console; 2) adiciona classe kit-js-ok no
 * <html> — inspecione e confira <html class="... kit-js-ok">.
 */
( function () {
	document.documentElement.classList.add( 'kit-js-ok' );
	console.log( '[HTML Templates Lite] JS do kit-header carregado via {{assets_url}}' );
} )();
