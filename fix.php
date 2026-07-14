<?php
$content = file_get_contents('app/Livewire/EventHotelPlanEditor.php');

$search = <<<PHP
        \$this->copySourceDay = (int) (\$this->stays[0]['day'] ?? 1);
        \$this->copyTargetDays = collect(\$this->stays)->pluck('day')->map(fn (\$d) => (int) \$d)->all();
            }

                app(EventHotelPlanService::class)->syncPayloadCollectionFromStayIndex(
            \$this->stays,
            \$this->activeStayIndex
        );
    }

            \$masters = collect(\$this->stays)->filter(fn (array \$stay) => empty(\$stay['same_as_day']));
        if (\$masters->count() !== 1) {
            return false;
        }

        \$masterDay = (int) (\$masters->first()['day'] ?? 0);

        return collect(\$this->stays)
            ->filter(fn (array \$stay) => (int) (\$stay['day'] ?? 0) !== \$masterDay)
            ->every(fn (array \$stay) => (int) (\$stay['same_as_day'] ?? 0) === \$masterDay);
    }
PHP;

$replace = <<<PHP
        \$this->copySourceDay = (int) (\$this->stays[0]['day'] ?? 1);
        \$this->copyTargetDays = collect(\$this->stays)->pluck('day')->map(fn (\$d) => (int) \$d)->all();
    }
PHP;

$content = str_replace($search, $replace, $content);
file_put_contents('app/Livewire/EventHotelPlanEditor.php', $content);
