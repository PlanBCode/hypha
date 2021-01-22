/**
 * WYMeditor : what you see is What You Mean web-based editor
 * Copyright (c) 2005 - 2009 Jean-Francois Hovinne, http://www.wymeditor.org/
 * Dual licensed under the MIT (MIT-license.txt)
 * and GPL (GPL-license.txt) licenses.
 *
 * For further information visit:
 *        http://www.wymeditor.org/
 *
 * File Name:
 *        jquery.wymeditor.embed.js
 *        Experimental embed plugin
 *
 * File Authors:
 *        Jonatan Lundin (jonatan.lundin a-t gmail dotcom)
 *        Roger Hu (roger.hu a-t gmail dotcom)
 *        Scott Nixon (citadelgrad a-t gmail dotcom)
 */

(function () {
    function removeItem(item, arr) {
        for (var i = arr.length; i--;) {
            if (arr[i] === item) {
                arr.splice(i, 1);
            }
        }
        return arr;
    }
    if (WYMeditor && WYMeditor.XhtmlValidator._tags.param.attributes) {

        WYMeditor.XhtmlValidator._tags.embed = {
            "attributes":[
                "allowscriptaccess",
                "allowfullscreen",
                "height",
                "src",
                "type",
                "width"
            ]
        };

        WYMeditor.XhtmlValidator._tags.param.attributes = {
            '0':'name',
            '1':'type',
            'valuetype':/^(data|ref|object)$/,
            '2':'valuetype',
            '3':'value'
        };

        WYMeditor.XhtmlValidator._tags.iframe = {
            "attributes":[
                "allowfullscreen",
                "width",
                "height",
                "src",
                "title",
                "frameborder"
            ]
        };

        WYMeditor.XhtmlValidator._tags.video = {
            "attributes":[
                "width",
                "height",
                "autoplay",
                "controls",
                "loop",
                "muted",
                "poster",
                "preload",
                "src"
            ]
        };

        WYMeditor.XhtmlValidator._tags.audio = {
            "attributes":[
                "autoplay",
                "controls",
                "loop",
                "muted",
                "preload",
                "src",
                "crossorigin",
                "currentTime",
                "disableRemotePlayback"
            ]
        };

        WYMeditor.XhtmlValidator._tags.source = {
            "attributes":[
                "src",
                "srcset",
                "type",
                "media",
                "sizes",
                "autoPictureInPicture",
                "buffered",
                "controlslist",
                "crossorigin",
                "currentTime",
                "disablePictureInPicture",
                "disableRemotePlayback",
                "intrinsicsize",
                "playsinline"
            ]
        };

        // Override the XhtmlSaxListener to allow param, embed and iframe.
        //
        // We have to do an explicit override
        // of the function instead of just changing the startup parameters
        // because those are only used on creation, and changing them after
        // the fact won't affect the existing XhtmlSaxListener
        var XhtmlSaxListener = WYMeditor.XhtmlSaxListener;
        WYMeditor.XhtmlSaxListener = function () {
            var listener = XhtmlSaxListener.call(this);
            // param, embed, iframe and source should be inline tags so
            // that they can be nested inside other elements, audio and
            // video block tags so source can be nested in them.
            removeItem('param', listener.block_tags);
            listener.inline_tags.push('param');
            listener.inline_tags.push('embed');
            listener.inline_tags.push('iframe');
            listener.block_tags.push('audio');
            listener.block_tags.push('video');
            listener.inline_tags.push('source');

            return listener;
        };

        WYMeditor.XhtmlSaxListener.prototype = XhtmlSaxListener.prototype;
    }
})();
