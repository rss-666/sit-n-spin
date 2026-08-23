( function () {
    'use strict';

    const config = window.pdrsPduTracks || { tracks: [] };
    if ( ! Array.isArray( config.tracks ) || ! config.tracks.length ) {
        return;
    }

    const normalize = ( value ) => String( value || '' ).trim().toLocaleLowerCase().replace( /\s+/g, ' ' );
    const tracks = new Map();
    config.tracks.forEach( ( track ) => tracks.set( normalize( track.title ), track ) );

    function openPlayer( track ) {
        const dialog = document.querySelector( '[data-pdrs-dialog]' );
        if ( ! dialog || 'function' !== typeof dialog.showModal ) {
            window.location.href = track.trackUrl;
            return;
        }
        const frame = dialog.querySelector( '[data-pdrs-dialog-frame]' );
        const title = dialog.querySelector( '[data-pdrs-dialog-title]' );
        const artist = dialog.querySelector( '[data-pdrs-dialog-artist]' );
        const lyricsWrap = dialog.querySelector( '[data-pdrs-dialog-lyrics-wrap]' );
        const lyrics = dialog.querySelector( '[data-pdrs-dialog-lyrics]' );
        const fullLink = dialog.querySelector( '[data-pdrs-dialog-link]' );
        frame.src = track.embedUrl;
        title.textContent = track.title;
        artist.textContent = track.artist || '';
        artist.hidden = ! track.artist;
        lyrics.textContent = track.lyrics || '';
        lyricsWrap.hidden = ! track.lyrics;
        lyrics.scrollTop = 0;
        fullLink.href = track.trackUrl;
        dialog.showModal();
    }

    function closePlayer( dialog ) {
        const frame = dialog.querySelector( '[data-pdrs-dialog-frame]' );
        const lyrics = dialog.querySelector( '[data-pdrs-dialog-lyrics]' );
        frame.src = 'about:blank';
        lyrics.textContent = '';
        dialog.close();
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        document.querySelectorAll( '[data-pdu-track]' ).forEach( function ( row ) {
            if ( row.getAttribute( 'data-src' ) ) {
                return; // A local file exists, so the theme's native player wins.
            }
            const titleElement = row.querySelector( 'p' );
            const track = tracks.get( normalize( titleElement ? titleElement.textContent : '' ) );
            if ( ! track ) {
                return;
            }
            const button = row.querySelector( '[data-pdu-play]' );
            if ( ! button ) {
                return;
            }
            button.addEventListener( 'click', function ( event ) {
                event.preventDefault();
                event.stopImmediatePropagation();
                openPlayer( track );
            }, true );
        } );

        const dialog = document.querySelector( '[data-pdrs-dialog]' );
        if ( ! dialog ) {
            return;
        }
        dialog.querySelector( '[data-pdrs-dialog-close]' ).addEventListener( 'click', function () {
            closePlayer( dialog );
        } );
        dialog.addEventListener( 'click', function ( event ) {
            if ( event.target === dialog ) {
                closePlayer( dialog );
            }
        } );
        dialog.addEventListener( 'cancel', function ( event ) {
            event.preventDefault();
            closePlayer( dialog );
        } );
    } );
}() );
