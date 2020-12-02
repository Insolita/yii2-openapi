<?php
/**
 * @var \cebe\yii2openapi\lib\items\DbModel $model
 * @var string $namespace
 * @var string $relationNamespace
 **/
use yii\helpers\Inflector;
use yii\helpers\VarDumper;

?>
<?= '<?php' ?>


namespace <?= $namespace ?>;

/**
 *<?= empty($model->description) ? '' : str_replace("\n", "\n * ", ' ' . trim($model->description)) ?>

 *
<?php foreach ($model->attributes as $attribute): ?>
 * @property <?= $attribute->getFormattedDescription() ?>

<?php endforeach; ?>
 *
<?php foreach ($model->relations as $relationName => $relation): ?>
<?php if ($relation->isHasOne()):?>
 * @property \<?= trim($relationNamespace, '\\') ?>\<?= $relation->getClassName() ?> $<?= Inflector::variablize($relation->getName()) ?>
<?php else:?>
 * @property array|\<?= trim($relationNamespace, '\\') ?>\<?= $relation->getClassName() ?>[] $<?= Inflector::variablize($relation->getName()) ?>
<?php endif?>

<?php endforeach; ?>
<?php foreach ($model->many2many as $relation): ?>
 * @property array|\<?= trim($relationNamespace, '\\') ?>\<?= $relation->relatedClassName ?>[] $<?= Inflector::variablize($relation->name) ?>

<?php endforeach; ?>
 */
abstract class <?= $model->getClassName() ?> extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return <?= var_export($model->getTableAlias()) ?>;
    }

    public function rules()
    {
        return <?=$model->getValidationRules()?>;
    }
<?php foreach ($model->relations as $relationName => $relation): ?>

    public function get<?= $relation->getCamelName() ?>()
    {
        return $this-><?= $relation->getMethod() ?>(\<?= trim($relationNamespace, '\\') ?>\<?= $relation->getClassName() ?>::class, <?php
            echo $relation->linkToString()?>);
    }
<?php endforeach; ?>
<?php foreach ($model->many2many as $relation): ?>

    public function get<?= $relation->getCamelName() ?>()
    {
        return $this->hasMany(\<?= trim($relationNamespace, '\\') ?>\<?= $relation->relatedClassName ?>::class, <?php
            echo $relation->linkToString($relation->link)?>)
<?php if (!$relation->hasViaModel):?>
                    ->viaTable(<?=VarDumper::export($relation->viaTableName)?>, <?=$relation->linkToString($relation->viaLink)?>);
<?php else:?>
                    ->via('<?=lcfirst($relation->getViaRelationName())?>');
<?php endif;?>
    }
<?php endforeach; ?>
}
