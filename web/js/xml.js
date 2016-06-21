(function ($) {

    // Main object initialization
    window.$xml = $.extend(window.$xml !== undefined ? window.$xml : {}, {

        url: '',

        config: {
            debug: true
        },

        log: function (message) {
            if ($xml.config.debug) {
                console.log(message);
            }
        },

        nodeAttributes: function (attributes) {
            var nodeContent = '';
            if (attributes && attributes.length > 0) {
                jQuery.each(attributes, function (index, attribute) {
                    nodeContent += ' <span class="attribute">'
                        + attribute.name
                        + '</span>=<span class="attributeValue">"'
                        + attribute.value + '"</span>';
                });
            }

            return nodeContent;
        },

        nodeId: function (id) {
            id = id.substr(1).replace(':', '---');
            var idParts = id.split('/');
            return idParts.join('--');
        },

        idToPath: function (id, step) {
            var pathObj = {};
            var parts = id.split('_');
            pathObj.sequence = parts.pop();
            pathObj.step = step;
            pathObj.path = parts.join('_').replace(/---/g, ':').replace(/--/g, '/');
            return pathObj;
        },

        openNode: function (name, value, attributes, children) {

            var nodeContent = '&lt;<span class="node">' + name + '</span>';

            nodeContent += $xml.nodeAttributes(attributes);


            if (children && children.length <= 0) {
                if (undefined !== value) {
                    nodeContent += '&gt;';
                    nodeContent += value;
                    nodeContent += '&lt;/<span class="node">' + name + '</span>&gt;';
                } else {
                    nodeContent += '/&gt;';
                }
            } else {
                nodeContent += '&gt;';
            }

            return $('<div class="ignoreMe"/>').html(nodeContent);
        },

        closeNode: function (name) {

            var nodeContent = '&lt;/<span class="node">' + name + '</span>&gt;';
            return $('<div class="ignoreMe"/>').html(nodeContent);
        },

        childNode: function (id, name, value, attributes) {

            var childElement = $('<div id="' + id + '" class="' + name + ' nodeChild" />');
            var expander = $('<a href="#" data-target="' + id + '" data-step="1" class="nodeExpand clickable">+</a>');

            expander.click(function (event) {
                event.preventDefault();

                if ($(this).text() == '+') {
                    var target = $(this).attr('data-target');
                    var step = $(this).attr('data-step');
                    $('#' + target).html('loading...');

                    var pathObject = $xml.idToPath(target, step);
                    var newUrl = $xml.url
                        + '/'
                        + encodeURIComponent(pathObject.path + '#' + pathObject.sequence + '#' + pathObject.step);

                    $xml.log('url: ' + newUrl);

                    $.ajax({
                        url: newUrl,
                        dataType: "text",
                        error: function (data) {
                            $xml.log('error');
                            $xml.log(data);
                        },
                        success: function (data) {
                            $xml.log(data);
                            $('#' + target).empty();
                            $xml.insertXml(target, JSON.parse(data));
                        }
                    });
                }
                return false;
            });

            childElement.append(expander);

            var nodeContent = '&lt;<span class="node">' + name + '</span>'
                + $xml.nodeAttributes(attributes)
                + '&gt;&lt;/<span class="node">' + name
                + '</span>&gt';

            childElement.append($('<span/>').html(nodeContent));
            return childElement;
        },

        /**
         * insert an xml object into dom
         * @param rootNode node to put the xml under
         * @param nodeObject xml node object
         */
        insertXml: function (rootNode, nodeObject) {
            var nodeData = nodeObject.content;
            var rootElement = $('#' + rootNode);
            var id = $xml.nodeId(nodeData.id);
            var nodeElement = $('<div id="' + id + '" class="' + nodeData.name + '" />');

            $xml.log('rootnode: ' + rootNode);
            $xml.log(nodeData);

            nodeElement.append($xml.openNode(nodeData.name, nodeData.value, nodeData.attributes, nodeData.children));

            if (nodeData.children.length > 0) {
                jQuery.each(nodeData.children, function (index, childNode) {
                    nodeElement.append($xml.childNode($xml.nodeId(childNode.id), childNode.name, childNode.value, childNode.attributes));
                });

                nodeElement.append($xml.closeNode(nodeData.name));
            }

            rootElement.append(nodeElement);
        }

    });

})(jQuery);
