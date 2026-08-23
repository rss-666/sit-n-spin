( function () {
    'use strict';

    function updateMode( select ) {
        document.querySelectorAll( '[data-pdrs-mode-panel]' ).forEach( function ( panel ) {
            const modes = panel.dataset.pdrsModePanel.split( ',' ).map( ( mode ) => mode.trim() );
            panel.hidden = ! modes.includes( select.value );
        } );
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        document.querySelectorAll( '[data-pdrs-mode]' ).forEach( function ( select ) {
            updateMode( select );
            select.addEventListener( 'change', function () { updateMode( select ); } );
        } );

        document.querySelectorAll( '[data-pdrs-select-audio]' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                if ( ! window.wp || ! wp.media ) {
                    return;
                }
                const frame = wp.media( {
                    title: 'Choose a local audio file',
                    button: { text: 'Use this audio' },
                    library: { type: 'audio' },
                    multiple: false
                } );
                frame.on( 'select', function () {
                    const attachment = frame.state().get( 'selection' ).first().toJSON();
                    const target = document.getElementById( button.dataset.target );
                    if ( target ) {
                        target.value = attachment.url || '';
                        target.dispatchEvent( new Event( 'change', { bubbles: true } ) );
                    }
                } );
                frame.open();
            } );
        } );
    } );
}() );
