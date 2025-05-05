( function( blocks, blockEditor, element ) {
    var el = element.createElement;
    blocks.registerBlockType('news/newsfeed', {
        title: 'News Feed',
        icon: 'rss',
        category: 'widgets',
        edit: function() {
            return el('p', {}, '8Realms News Feed preview in editor.');
        },
        save: function() {
            return el('div', { className: 'news-feed' }, 'News Feed will render here.');
        }
    });
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element );