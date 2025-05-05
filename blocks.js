( function( blocks, editor, element ) {
    var el = element.createElement;
    blocks.registerBlockType( 'news/newsfeed', {
        title: 'News Feed',
        icon: 'rss',
        category: 'widgets',
        edit: function() {
            return el('p', {}, '8Realms News Feed preview in editor.');
        },
        save: function() {
            return el('div', {}, '[news/newsfeed]');
        }
    } );
} )( window.wp.blocks, window.wp.editor, window.wp.element );