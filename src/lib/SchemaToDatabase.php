<?php

/**
 * @copyright Copyright (c) 2018 Carsten Brandt <mail@cebe.cc> and contributors
 * @license https://github.com/cebe/yii2-openapi/blob/master/LICENSE
 */

namespace cebe\yii2openapi\lib;

use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Yii;
use yii\base\Component;

/**
 * Convert OpenAPI description into a database schema.
 * There are two options:
 * 1. let the generator guess which schemas need a database table
 *    for storing their data and which do not.
 * 2. Explicitly define schemas which represent a database table by adding the
 *    `x-table` property to the schema.
 * The [[]]
 *
 * OpenApi Schema definition rules for database conversion:
 * https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.3.md#schema-object
 *
 * components:
 *     schemas:
 *        ModelName: #(table name becomes model_names)
 *            description: #(optional, become as model class comment)
 *            required: #(list of required property names that can't be nullable)
 *               - id
 *               - some
 *            x-table: custom_table #(explicit database table name)
 *            x-pk: pid #(optional, primary key name if it called not "id") (composite keys not supported yet)
 *            properties: #(table columns and relations)
 *               prop_name:
 *                  type: #(one of common types string|integer|number|boolean|array)
 *                  format: #(see https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.3.md#dataTypes)
 *                  readOnly: true/false #(If true, should be skipped from validation rules)
 *                  minimum: #(numeric value, applied for validation rules and faker generation)
 *                  maximum: #(numeric value, applied for integer|number validation rules and faker generation)
 *                  maxLength: #(numeric value, applied for database column size limit!, also can be applied for validation)
 *                  minLength: #(numeric value, can be applied for validation rules)
 *                  default: #(int|string, default value, used for database migration and model rules)
 *                  x-db-type: #(Custom database type like JSON, JSONB, CHAR, VARCHAR, UUID, etc )
 *                  x-db-unique: true #(mark unique attribute for database and validation constraining)
 *                  x-faker: #(custom faker generator, for ex '$faker->gender')
 *                  description: #(optional, used for comment)
 *
 */
class SchemaToDatabase extends Component
{

    /**
     * @var array List of model names to exclude.
     */
    public $excludeModels = [];

    /**
     * @var array Generate database models only for Schemas that have the `x-table` annotation.
     */
    public $generateModelsOnlyXTable = false;

    public $attributeResolverClass = AttributeResolver::class;

    /**
     * @param \cebe\openapi\spec\OpenApi $openApi
     * @return array|\cebe\yii2openapi\lib\items\DbModel[]
     * @throws \cebe\openapi\exceptions\UnresolvableReferenceException
     * @throws \yii\base\InvalidConfigException
     */
    public function generateModels(OpenApi $openApi):array
    {
        $models = [];
        foreach ($openApi->components->schemas as $schemaName => $schema) {
            if ($schema instanceof Reference) {
                $schema->getContext()->mode = ReferenceContext::RESOLVE_MODE_INLINE;
                $schema = $schema->resolve();
            }

            if (!$this->canGenerateModel($schemaName, $schema)) {
                continue;
            }
            $resolver = Yii::createObject($this->attributeResolverClass, [$schemaName, $schema]);
            $models[$schemaName] = $resolver->resolve();
        }

        // TODO generate inverse relations

        return $models;
    }

    private function canGenerateModel(string $schemaName, Schema $schema):bool
    {
        // only generate tables for schemas of type object and those who have defined properties
        if ((empty($schema->type) || $schema->type === 'object') && empty($schema->properties)) {
            return false;
        }
        if (!empty($schema->type) && $schema->type !== 'object') {
            return false;
        }
        // do not generate tables for composite schemas
        if ($schema->allOf || $schema->anyOf || $schema->multipleOf || $schema->oneOf) {
            return false;
        }
        // skip excluded model names
        if (in_array($schemaName, $this->excludeModels, true)) {
            return false;
        }

        if ($this->generateModelsOnlyXTable && empty($schema->{CustomSpecAttr::TABLE})) {
            return false;
        }
        return true;
    }
}