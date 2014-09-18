<?php
namespace Craft;

class ManyToManyService extends BaseApplicationComponent
{

    var $element;

    public $allowed = array(
        'Entries',
    );

    /**
     * Returns all Entry Field Types
     * @return array
     */
    public function getAllEntryFields()
    {
        // Allowed Fields
        $allowedFields = array();

        // All Field
        $fields = craft()->fields->getAllFields();
        if (!empty($fields))
        {
            foreach ($fields as $field)
            {
                if (in_array($field->getFieldType()->name, $this->allowed))
                {
                    $allowedFields[$field->handle] = $field->name;
                }
            }
        }

        return $allowedFields;
    }

    /**
     * [getRelatedEntries returns]
     * Returns related entries from an element limited to a section
     * @param  [type] $element
     * @param  [type] $section
     * @return [type]
     */
    public function getRelatedEntries($element, $section, $field)
    {

        $criteria = craft()->elements->getCriteria(ElementType::Entry);
        $criteria->section = $section;
        $criteria->limit   = null;
        $criteria->relatedTo = array(
            'targetElement' => $element,
            'field'         => $field
        );
        $elements = craft()->elements->findElements($criteria);
        return $elements;
        
    }

    /**
     * [saveRelationship description]
     * @param  BaseFieldType $fieldType [description]
     * @return [type]                   [description]
     */
    public function saveRelationship(BaseFieldType $fieldType)
    {
        
        // Set the element ID of this element
        $targetId = $fieldType->element->id;

        // Delete cache related to this element ID
        craft()->templateCache->deleteCachesByElementId($targetId);

        // Get the post values for this field
        $handle      = $fieldType->model->handle;
        $content     = $fieldType->element->getContent();
        $postContent = $content->getAttribute($handle);

        // There are 3 Items we need to make up a unique relationship in the craft_relations table:
        // fieldId  --> We define this in the Field settings when creating it
        // sourceId --> The elementIds that create the relationship initially. This is currently stored in the $postContent array
        // targetId --> $elementId, this is the reverse of the relationship
        $fieldId = $postContent['singleField'];
        
        // The relationships we either want to add or leave
        $toAdd = array();
        if (!empty($postContent['add'])) {
            $toAdd = $postContent['add'];
        }

        // The relationships we want to remove
        $toDelete = array();
        if (!empty($postContent['delete'])) {
            $toDelete = $postContent['delete'];
        }
        
        // First handle adding or updating the relationships that have to exist
        if (!empty($toAdd)) {
            foreach ($toAdd as $sourceId) {
                
                // 1.) Check and see if this relationship already exists. If it does, do nothing.
                // 2.) If the relationship does NOT exist, create it.
                $exists = craft()->db->createCommand()
                    ->select('id')
                    ->from('relations')
                    ->where('fieldId = :fieldId', array(':fieldId' => $fieldId))
                    ->andWhere('sourceId = :sourceId', array(':sourceId' => $sourceId))
                    ->andWhere('targetId = :targetId', array(':targetId' => $targetId))
                    ->queryColumn();
                
                // The relationship doesn't exist. Add it! For now, the relationship get's added to the beginning
                // of the sort order. This could change.
                if (empty($exists)) {
                    $columns = array(
                        'fieldId'      => $fieldId,
                        'sourceId'     => $sourceId,
                        'sourceLocale' => null,
                        'targetId'     => $targetId,
                        'sortOrder'    => 1);
                    craft()->db->createCommand()->insert('relations', $columns);
                }

            }
        }

        // Now, delete the existing relationships if the user removed them.
        if (!empty($toDelete)) {
            foreach ($toDelete as $sourceId) {

                $oldRelationConditions = array(
                    'and',
                    'fieldId = :fieldId',
                    'sourceId = :sourceId',
                    'targetId = :targetId'
                );
                $oldRelationParams = array(
                    ':fieldId'  => $fieldId,
                    ':sourceId' => $sourceId,
                    ':targetId' => $targetId
                );

                craft()->db->createCommand()->delete('relations', $oldRelationConditions, $oldRelationParams);

            }
        }

    }

    /**
     * Checks for Same Side Relationship status and add and/or delete if necessary
     * @param  array  $entries        
     * @param  string $fieldHandle    
     * @param  int $currentEntry   
     * @param  string $currentSection 
     * @return null               
     */
    public function processSameSideRelationships($rawEntries = array(), $fieldHandle = null, $currentEntry = null, $currentSection = null)
    {
        // Filter so that only entries from this section are available.
        // This is so you can still use the same filed to manage relationships
        // from one side, but to tie two together, they have to be in the 
        // same section.
        $entries = $this->sanitizeEntries($rawEntries, $currentSection);

        // Field Info
        if (empty($fieldHandle)) return;
        $field   = craft()->fields->getFieldByHandle($fieldHandle);
        $fieldId = $field->id;

        // Check if this field is setup as translatable, and if it is: exit. Not supported currently.
        if ($field->translatable) return;
        
        // Check for the existing relationship and if it's not there, add it.
        if (!empty($entries))
        {
            foreach ($entries as $entryId)
            {
                $exists = craft()->db->createCommand()
                    ->select('id')
                    ->from('relations')
                    ->where('fieldId = :fieldId', array(':fieldId' => $fieldId))
                    ->andWhere('sourceId = :sourceId', array(':sourceId' => $entryId))
                    ->andWhere('targetId = :targetId', array(':targetId' => $currentEntry))
                    ->queryColumn();
                // The relationship doesn't exist, create it.
                if (empty($exists)) {
                    $columns = array(
                        'fieldId'   => $fieldId,
                        'sourceId'  => $entryId,
                        'targetId'  => $currentEntry,
                        'sortOrder' => 0
                        );
                    craft()->db->createCommand()->insert('relations', $columns);
                }
            }
        }

        // That was the easy part. The trickier part is deleting relationships
        // that may have existed but subsequently got deleted by the user. We
        // have to query the DB and see if there are any relationships with this
        // entry and field where this entry is set as the target. Then, we have
        // to check and see if the sibling relationship exists at all. If it does,
        // we leave it, but if it doesn't, that means the relationships was removed
        // and we have to also delete the record.
        $toDelete = craft()->db->createCommand()
            ->select('id, sourceId')
            ->from('relations')
            ->where('fieldId = :fieldId', array(':fieldId' => $fieldId))
            ->andWhere('targetId = :targetId', array(':targetId' => $currentEntry))
            ->queryAll();
        if (!empty($toDelete))
        {
            foreach ($toDelete as $id)
            {
                // First, check if this source ID is in the supported section!
                // This check allows the relationship with this entry and field to
                // exist in other places throughout the CMS and will NOT manage it.
                $entriesSection = craft()->entries->getEntryById($id);
                if($entriesSection->section->handle == $currentSection)
                {
                    // Now, check if the sibling relationship exists.
                    $exists = craft()->db->createCommand()
                        ->select('id')
                        ->from('relations')
                        ->where('fieldId = :fieldId', array(':fieldId' => $fieldId))
                        ->andWhere('sourceId = :sourceId', array(':sourceId' => $currentEntry))
                        ->andWhere('targetId = :targetId', array(':targetId' => $id['sourceId']))
                        ->queryColumn();
                    // It doesn't exist, meaning this relationship has no sibling. 
                    // Currently it's orphaned from it's two way association. Delete it.
                    if (empty($exists))
                    {
                        $oldRelationConditions = array(
                            'and',
                            'id = :id'
                        );
                        $oldRelationParams = array(
                            ':id'  => $id['id']
                        );
                        craft()->db->createCommand()->delete('relations', $oldRelationConditions, $oldRelationParams);
                    }
                }
            }
        }
    }

    /**
     * Returns a list of entries in the defined section
     * @param  array $entries 
     * @param  string $section 
     * @return array
     */
    private function sanitizeEntries($entries, $section)
    {
        $filteredEntries = array();
        if (!empty($entries))
        {
            foreach ($entries as $e)
            {
                $entry = craft()->entries->getEntryById($e);
                if ($section === $entry->section->handle)
                {
                    $filteredEntries[] = $entry->id;
                }
            }
        }
        return $filteredEntries;
    }

}
