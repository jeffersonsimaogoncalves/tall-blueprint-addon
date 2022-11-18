<?php

namespace Tanthammar\TallBlueprintAddon\Tasks;

use Blueprint\Models\Model;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Tanthammar\TallBlueprintAddon\Contracts\Task;

class AddRelationshipFields implements Task
{
    use InteractWithRelationships;

    protected const INDENT = '            ';

    public function handle(array $data, Closure $next): array
    {
        $model = $data['model'];
        $fields = $data['fields'];
        $imports = $data['imports'];
        $relationships = $model->relationships();

        ksort($relationships);

        foreach ($relationships as $type => $references) {
            foreach ($references as $reference) {
                if (Str::contains($reference, ':')) {
                    [$class, $name] = explode(':', $reference);
                } else {
                    $name = $reference;
                    $class = null;
                }

                $name = Str::beforeLast($name, '_id');
                $class = Str::studly($class ?? $name);

                $methodName = $this->buildMethodName($name, $type);
                $label = Str::studly($methodName);

                $fieldType = $this->fieldType($type);
                $imports[] = $fieldType;

                if ($type === 'morphto') {
                    $label .= 'able';
                }

                $fields .= self::INDENT.$fieldType."::make('".$label."'";


                if ($type !== 'morphto' && $this->classNameNotGuessable($label, $class)) {
//                    $fields .= ", '".$methodName."', ".$class.'::class'; //sets third option in make() command. Example make('Author', 'user_id', User::class)
                    $fields .= ", '".$methodName."'";
                }

                $fields .= ')';


                $fields .= match ($fieldType) {
                    'Select', 'MultiSelect' => '->options(/* TODO pass Array|Collection $' . $label . 'Options */)',
                    'KeyVal', 'Repeater' => "->fields([/* TODO add {$label} fields */])",
                };

                $fields .= "->relation(/* TODO create a save{$label}() event hook */)";

                if ($this->isNullable($reference, $model)) {
                    $fields .= "->rules('nullable')";
                }

                $fields .= ','.PHP_EOL;
            }

            $fields .= PHP_EOL;
        }

        $data['fields'] = $fields;
        $data['imports'] = $imports;

        return $next($data);
    }

    private function buildMethodName(string $name, string $type): string
    {
        static $pluralRelations = [
            'belongstomany',
            'hasmany',
            'morphmany',
        ];

        return in_array(strtolower($type), $pluralRelations, false)
            ? Str::plural($name)
            : $name;
    }

    private function classNameNotGuessable($label, $class): bool
    {
        return $label !== $class
            && $label !== Str::plural($class);
    }

    private function isNullable($relation, Model $model): bool
    {
        $relationColumnName = $this->relationshipIdentifiers($model->columns())
            ->filter(function ($relationReference, $columnName) use ($relation, $model) {
                return in_array($relationReference, Arr::get($model->relationships(), 'belongsTo', []), false)
                    && $columnName === $relation;
            })
            ->first();

        return ! is_null($relationColumnName)
            && in_array('nullable', $model->columns()[$relationColumnName]->modifiers(), false);
    }

    private function fieldType(string $dataType): string
    {
        static $fieldTypes = [
            'belongsto' => 'Select', //BelongsTo
            'belongstomany' => 'MultiSelect', //BelongsToMany type multiple
            'hasone' => 'KeyVal', //HasOne
            'hasmany' => 'Repeater', //HasMany
            'morphto' => 'Select', //MorphTo, get the Parent morpheable model
            'morphone' => 'KeyVal', //MorphOne, example has one image()
            'morphmany' => 'Repeater', //MorphMany
        ];

        return $fieldTypes[strtolower($dataType)];
    }
}
