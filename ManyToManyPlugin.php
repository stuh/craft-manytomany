<?php
namespace Craft;

class ManyToManyPlugin extends BasePlugin
{
    
    /**
     * Runs on Craft's initilization
     * @return null
     */
    public function init()
    {
        parent::init();
        if (craft()->request->isCpRequest()) {
            craft()->templates->includeJsResource('manytomany/js/hide-input.js');
        }
    }

    /**
     * Returns the name of the plugin
     * @return string
     */
    public function getName()
    {
        return Craft::t('Many to Many');
    }

    /**
     * Plugin version
     * @return string
     */
    public function getVersion()
    {
        return '0.1';
    }

    /**
     * Developer Name
     * @return string
     */
    public function getDeveloper()
    {
        return 'Page 8';
    }

    /**
     * Developer URL
     * @return string
     */
    public function getDeveloperUrl()
    {
        return 'http://www.page-8.com';
    }

    /**
     * The settings for the plugin. This is new as of 0.2.0 and is used 
     * for mapping relationships across the same field and section.
     * @return string|null
     */
    public function getSettingsHtml()
    {
        // Group the Sections into an array
        $sections    = array();
        $allSections = craft()->sections->getAllSections();
        if (!empty($allSections))
        {
            foreach($allSections as $section)
            {
                $sections[$section->handle] = $section->name;
            }
        }

        // Load the Namespace for the JS
        craft()->templates->includeJs('
            var currentNamespace  = "' . craft()->templates->getNamespace() . '";
            var currentIncrement  = 1;
            var currentSelections = '.json_encode($this->getSettings()->selectedFieldsPlusSection).';
        ');

        // Render the Template
        return craft()->templates->render('manytomany/settings.twig', array(
            'fields'    => craft()->manyToMany->getAllEntryFields(),
            'sections'  => $sections,
        ));
    }

    /**
     * The settings used by the plugin.
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'selectedFieldsPlusSection' => array(AttributeType::Mixed, 'label' => 'Fields and Sections that Have the Relationship', 'default' => array()),
        );
    }

}
