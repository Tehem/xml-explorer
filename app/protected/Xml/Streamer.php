<?php
/**
 * Streamer.php
 *
 * @category  xmlExplorer
 * @package   Tehem\Xml
 * @author    Tehem <root@tehem.net>
 *
 * @since     21/04/15 10:42
 * @copyright 2016 Tehem.net - All Rights Reserved
 */

namespace Tehem\Xml;

use Psr\Log\LoggerInterface;
use XMLReader;

/**
 * Class Streamer : basic XML reader-based class
 * to retrieve portion of XML file in a JS-ready format
 * to display on a web page
 *
 * @package Windataco\Xml
 */
class Streamer
{
    /**
     * @var XMLReader
     */
    protected $xmlReader = null;

    /**
     * @var LoggerInterface
     */
    protected $logger = null;


    /**
     * Current xml file name
     *
     * @var string
     */
    private $name;

    /**
     * Current xml complete file path
     *
     * @var string
     */
    private $path = null;

    /**
     * Final structure from XML
     *
     * @var array
     */
    private $node;

    /**
     * @var array
     */
    private $currentNode = null;

    /**
     * @var string current node path
     */
    private $currentXmlPath = null;

    /**
     * @var int
     */
    private $currentXmlDepth = 0;

    /**
     * @var string node path we are looking for
     */
    private $targetNode     = null;

    /**
     * @var int minimum depth to start looking
     */
    private $startDepth     = 0;

    /**
     * @var int maximum depth to lookup nodes
     */
    private $targetDepth    = 1;

    /**
     * @var int target sequence number of target node
     */
    private $targetSequence = 1;

    /**
     * @var bool is node found and retrieved ?
     */
    private $nodeDone = false;

    /**
     * @var array node sequences history
     */
    private $history = array();

    /**
     * @param XMLReader       $xmlReader xml reader instance
     * @param LoggerInterface $logger    logger
     */
    public function __construct(XMLReader $xmlReader, LoggerInterface $logger)
    {
        $this->xmlReader = $xmlReader;
        $this->logger    = $logger;
    }

    /**
     * @param string $path path to xml file
     *
     */
    public function setPath($path)
    {
        $this->path = $path;
        $this->name = basename($this->path);
    }

    /**
     * @param string $nodePath  path to node
     * @param int    $sequence  sequence for a specific node
     * @param int    $depthStep sub depth to include
     *
     * @return mixed
     */
    public function loadNode($nodePath = null, $sequence = 1, $depthStep = 1)
    {
        if (null == $this->path) {
            throw new MissingFileException('Path to file is not set');
        }

        $this->node        = array();
        $this->currentNode = &$this->node;

        $this->targetSequence = $sequence;
        $this->targetNode     = $nodePath;

        $this->currentXmlPath  = '';
        $this->currentXmlDepth = 0;

        // defaut: root node
        if (null == $nodePath) {
            $this->targetNode  = null;
            $this->startDepth  = 0;
            $this->targetDepth = $depthStep;

        } else {
            $parts             = explode('/', $nodePath);
            $this->startDepth  = count($parts) - 2;
            $this->targetDepth = $this->startDepth + $depthStep;
        }

//        echo 'Target Node: ' . $this->targetNode . "\n";
//        echo 'Target Sequence: ' . $this->targetSequence  . "\n";
//        echo 'Start Depth: ' . $this->startDepth . "\n";
//        echo 'Target Depth: ' . $this->targetDepth . "\n";

        $this->xmlReader->open($this->path);

        while ($this->xmlReader->read() && !$this->nodeDone) {
            $this->handleNode();
        }

        return $this->node;
    }

    /**
     * Called for every node of XML document, call appropriate handler if present
     */
    protected function handleNode()
    {
        $nodeType  = $this->xmlReader->nodeType;
        $nodeName  = $this->xmlReader->name;
        $nodeDepth = $this->xmlReader->depth;

        if (XMLReader::SIGNIFICANT_WHITESPACE == $nodeType) {
            return;
        }

        if (XMLReader::TEXT == $nodeType || XMLReader::CDATA == $nodeType) {
            if ($this->currentXmlPath == $this->targetNode
                && $this->history[$this->currentXmlPath] == $this->targetSequence
            ) {
                // node text
                $this->currentNode['value'] = $this->xmlReader->value;
            }
        }

        if ($nodeDepth > $this->currentXmlDepth) {
            // path is growing
            $this->currentXmlPath = $this->currentXmlPath . '/' . $nodeName;

        } elseif ($nodeDepth < $this->currentXmlDepth) {
            // path is reducing
            $this->currentXmlPath = dirname(dirname($this->currentXmlPath)) . '/' . $nodeName;

        } else {
            // path is same (sibling)
            $this->currentXmlPath = dirname($this->currentXmlPath) . '/' . $nodeName;
        }

        $this->currentXmlDepth = $nodeDepth;

        if (XMLReader::ELEMENT == $nodeType) {
            // ignore subsequent elements
            if ($this->targetDepth < $nodeDepth) {
                return;
            }

            if (null == $this->targetNode) {
                $this->targetNode = $this->currentXmlPath;
            }

            // keep track of current sequence for current node
            if (!array_key_exists($this->currentXmlPath, $this->history)) {
                $this->history[$this->currentXmlPath] = 0;
            }

            $this->history[$this->currentXmlPath]++;

//            echo '(D:' . $this->currentXmlDepth . ') ' .
//                $this->currentXmlPath . " - SEQ: " .
//                $this->history[$this->currentXmlPath] . "\n";

            // this is the node we are looking for
            if ($this->currentXmlPath == $this->targetNode
                && $this->history[$this->currentXmlPath] == $this->targetSequence
            ) {
//                echo "MATCH! " . $this->currentXmlPath . " SEQ " . $this->targetSequence . "\n";

                $this->currentNode = array(
                    'id'         => $this->currentXmlPath . '_' . $this->history[$this->currentXmlPath],
                    'name'       => $this->xmlReader->localName,
                    'depth'      => $this->currentXmlDepth,
                    'attributes' => array(),
                    'children'   => array()
                );

                if ($this->xmlReader->hasAttributes) {
                    while ($this->xmlReader->moveToNextAttribute()) {
                        $this->currentNode['attributes'][] = array(
                            'name'  => $this->xmlReader->name,
                            'value' => $this->xmlReader->value
                        );
                    }
                }
            } elseif (false !== strpos($this->currentXmlPath, $this->targetNode)
                && array_key_exists($this->targetNode, $this->history)
                && $this->history[$this->targetNode] == $this->targetSequence
            ) {
                // our node DIRECT children
                if ($this->startDepth <= $this->xmlReader->depth) {
                    $children = array(
                        'id'         => $this->currentXmlPath . '_' . $this->history[$this->currentXmlPath],
                        'name'       => $this->xmlReader->localName,
                        'depth'      => $this->xmlReader->depth,
                        'attributes' => array()
                    );

                    if ($this->xmlReader->hasAttributes) {
                        while ($this->xmlReader->moveToNextAttribute()) {
                            $children['attributes'][] = array(
                                'name'  => $this->xmlReader->name,
                                'value' => $this->xmlReader->value
                            );
                        }
                    }

                    $this->currentNode['children'][] = $children;
                }
            }
        }

        if (XMLReader::END_ELEMENT == $nodeType) {
            if ($this->currentXmlPath == $this->targetNode
                && $this->history[$this->currentXmlPath] == $this->targetSequence
            ) {
                $this->nodeDone = true;

                return;
            }
        }
    }

    /**
     * Destruct instance, cleanup
     */
    public function __destruct()
    {
        $this->xmlReader->close();
    }
}
