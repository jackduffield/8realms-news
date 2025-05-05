( function( blocks, editor, element ) {
    var el = element.createElement;
    blocks.registerBlockType('8realms-news/newsfeed', {
        title: 'News Feed',
        icon: 'rss',
        category: 'widgets',
        edit: function() {
            return el('p', {}, '8Realms News Feed preview in editor.');
        },
        save: function() {
            return el('div', { className: '8realms-news-feed' }, 'News Feed will render here.');
        }
    });
} )( window.wp.blocks, window.wp.editor, window.wp.element );