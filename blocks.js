( function( blocks, blockEditor, element, components ) {
    var el = element.createElement;
    var ServerSideRender = components.ServerSideRender;

    blocks.registerBlockType('news/newsfeed', {
        title: 'News Feed',
        icon: 'rss',
        category: 'widgets',
        edit: function() {
            return el(ServerSideRender, {
                block: 'news/newsfeed',
            });
        },
        save: function() {
            // The content is rendered server-side, so save function returns null.
            return null;
        }
    });
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components );