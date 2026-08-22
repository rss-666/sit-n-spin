( function () {
    'use strict';

    document.addEventListener( 'click', function ( event ) {
        const confirmTarget = event.target.closest( '[data-trb-confirm]' );
        if ( confirmTarget && ! window.confirm( confirmTarget.dataset.trbConfirm ) ) {
            event.preventDefault();
            return;
        }

        const actionButton = event.target.closest( 'button[data-trb-action]' );
        if ( ! actionButton ) {
            return;
        }
        const form = actionButton.closest( 'form' );
        if ( ! form ) {
            return;
        }
        const action = form.querySelector( '#trb-form-action' );
        const nonce = form.querySelector( '#trb-form-nonce' );
        if ( action && nonce ) {
            action.value = actionButton.dataset.trbAction;
            nonce.value = actionButton.dataset.trbNonce;
        }
        if ( 'trb_generate' === actionButton.dataset.trbAction ) {
            // Keep the submit control enabled until the browser completes its
            // default form action. Disabling it during this click event can
            // cancel submission in some browsers and leave the UI stuck.
            actionButton.setAttribute( 'aria-busy', 'true' );
            actionButton.textContent = 'Generating…';
        }
    } );
}() );
