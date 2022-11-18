<?php

namespace Tanthammar\TallBlueprintAddon\Tasks;

use Closure;
use Tanthammar\TallBlueprintAddon\Contracts\Task;

class RemapImports implements Task
{
    public function handle(array $data, Closure $next): array
    {
        $data['imports'] = collect($data['imports'])
            ->unique()
            ->map(function ($type) {
                if(in_array($type, $this->freeFields(), false)) {
                    return 'use Tanthammar\TallForms\\'.$type.';';
                }
                return 'use Tanthammar\TallFormsSponsors\\'.$type.';';
            })
            ->prepend('use Tanthammar\TallForms\TallFormComponent;')
            ->sort(function ($a, $b) {
                return  strlen($a) - strlen($b) ?: strcmp($a, $b);
            })
            ->values()
            ->all();

        return $next($data);
    }

    public function freeFields(): array
    {
        return [
            'Checkbox',
            'Input',
            'Trix',
            'Select',
            'KeyVal',
            'Repeater'
        ];
    }
}
