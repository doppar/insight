# Doppar Insight

### Installation

#### DEV

```json
"repositories": [
    {
        "type": "path",
        "url": "../insight",
        "options": {
            "symlink": true
        }
    }
]
```

```bash
composer require doppar/insight
```

### Configuration

```php
"providers" => [
    ...
    Doppar\Insight\ProfilerServiceProvider::class,
],
```

