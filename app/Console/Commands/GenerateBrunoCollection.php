<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateBrunoCollection extends Command
{
    protected $signature = 'api:generate-bruno';

    protected $description = 'Generate Bruno collection for REST resources';

    protected string $basePath = 'bruno/personal-dashboard';

    public function handle(): void
    {
        $resourcePath = app_path('Rest/Resources');

        if (! File::exists($resourcePath)) {
            $this->error('Resources folder not found');

            return;
        }

        $resources = File::files($resourcePath);

        foreach ($resources as $resource) {

            $name = $resource->getFilenameWithoutExtension();
            $resourceName = Str::camel(Str::plural(str_replace('Resource', '', $name)));

            $folder = base_path($this->basePath.'/'.$resourceName);

            File::ensureDirectoryExists($folder);

            $this->generateSearch($folder, $resourceName);
            $this->generateDetails($folder, $resourceName);
            $this->generateMutate($folder, $resourceName);
            $this->generateCount($folder, $resourceName);

            $this->info("Generated Bruno endpoints for {$resourceName}");
        }
    }

    protected function generateSearch($folder, $resource): void
    {
        $content = <<<BRU
meta {
  name: {$resource} Search
  type: http
}

post {
  url: {{baseUrl}}/{$resource}/search
  body: json
}

body:json {
{
  "limit": 10
}
}
BRU;

        File::put("{$folder}/search.bru", $content);
    }

    protected function generateDetails($folder, $resource): void
    {
        $content = <<<BRU
meta {
  name: {$resource} Details
  type: http
}

post {
  url: {{baseUrl}}/{$resource}/details
  body: json
}

body:json {
{
  "id": 1
}
}
BRU;

        File::put("{$folder}/details.bru", $content);
    }

    protected function generateMutate($folder, $resource): void
    {
        $content = <<<BRU
meta {
  name: {$resource} Mutate
  type: http
}

post {
  url: {{baseUrl}}/{$resource}/mutate
  body: json
}

body:json {
{
  "create": []
}
}
BRU;

        File::put("{$folder}/mutate.bru", $content);
    }

    protected function generateCount($folder, $resource): void
    {
        $content = <<<BRU
meta {
  name: {$resource} Count
  type: http
}

post {
  url: {{baseUrl}}/{$resource}/count
}
BRU;

        File::put("{$folder}/count.bru", $content);
    }
}
