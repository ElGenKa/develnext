<?php
namespace ide\webplatform\formats;

use ide\editors\FormEditor;
use ide\formats\form\AbstractFormDumper;
use ide\formats\form\AbstractFormElementTag;
use ide\misc\EventHandlerBehaviour;
use ide\utils\Json;
use ide\webplatform\editors\WebFormEditor;
use ide\webplatform\formats\form\AbstractWebElement;
use php\format\JsonProcessor;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXPane;
use php\gui\UXButton;
use php\gui\UXLoader;
use php\gui\UXNode;
use php\io\IOException;
use php\io\MemoryStream;
use php\xml\DomDocument;
use php\xml\DomElement;

/**
 * Class WebFormDumper
 * @package ide\webplatform\formats
 */
class WebFormDumper extends AbstractFormDumper
{
    use EventHandlerBehaviour;

    /**
     * @var JsonProcessor
     */
    protected $json;

    /**
     * @var array
     */
    private $formElementTags;

    /**
     * @var array
     */
    private $formElementUiClasses;

    /**
     * WebFormDumper constructor.
     */
    public function __construct()
    {
        $this->json = new JsonProcessor(JsonProcessor::SERIALIZE_PRETTY_PRINT);
    }

    protected function loadFrmFile(WebFormEditor $editor, UXPane $layout)
    {
        $schema = Json::fromFile($editor->getFrmFile());

        $layout->data('--web-form', true);

        if ($layoutSchema = $schema['layout']) {
            if (isset($layoutSchema['size'])) {
                $layout->size = $layoutSchema['size'];
            }

            /** @var WebFormFormat $format */
            $format = $editor->getFormat();

            foreach ((array) $schema['layout']['_content'] as $item) {
                if ($element = $format->getWebElementByUiClass($item['_'])) {
                    $view = $element->createElement();
                    $element->loadUiSchema($view, $item);

                    $layout->add($view);
                }
            }
        }
    }

    public function load(FormEditor $editor)
    {
        $designer = $editor->getDesigner();

        /** @var UXAnchorPane $layout */
        try {
            $loader = new UXLoader();

            $memory = new MemoryStream();
            $memory->write('<?xml version="1.0" encoding="UTF-8"?>
<?import javafx.scene.*?>
<?import javafx.scene.layout.*?>
<AnchorPane maxHeight="-Infinity" maxWidth="-Infinity" minHeight="-Infinity" minWidth="-Infinity" prefHeight="480" prefWidth="640"
	xmlns="http://javafx.com/javafx/8" xmlns:fx="http://javafx.com/fxml/1">
</AnchorPane>
');
            $memory->seek(0);
            $layout = $loader->load($memory);

            $format = $editor->getFormat();

            if ($editor instanceof WebFormEditor) {
                $this->loadFrmFile($editor, $layout);
            }

            if ($layout instanceof UXPane) {
                $editor->setLayout($layout);
                $this->trigger('load', [$editor, $layout]);
            } else {
                throw new IOException();
            }

            return true;
        } catch (IOException $e) {
            $editor->setIncorrectFormat(true);
            $editor->setLayout(new UXAnchorPane());
            return false;
        }
    }

    public function save(FormEditor $editor)
    {
        /** @var WebFormEditor $editor */
        $this->trigger('save', [$editor]);

        $designer = $editor->getDesigner();

        $layout = $editor->getLayout();

        $uiContent = [];
        foreach ($layout->children as $child) {
            $element = $child->data('--web-element');

            if ($element instanceof AbstractWebElement) {
                $uiSchema = $element->uiSchema($child);
                $uiContent[] = $uiSchema;
            }
        }

        $uiFormSchema = [
            'title' => 'MainForm',
            'layout' => [
                '_' => 'AnchorPane',
                'size' => [$layout->width, $layout->height],
                '_content' => $uiContent
            ]
        ];

        Json::toFile($editor->getFrmFile(), $uiFormSchema);
    }

    /**
     * @param UXNode[] $nodes
     * @param DomDocument $document
     */
    public function appendImports(array $nodes, DomDocument $document)
    {
    }

    /**
     * @param FormEditor $editor
     * @param UXNode $node
     * @param DomDocument $document
     *
     * @param bool $ignoreUnregistered
     * @return DomElement
     */
    public function createElementTag(FormEditor $editor = null, UXNode $node, DomDocument $document, $ignoreUnregistered = true)
    {
    }
}