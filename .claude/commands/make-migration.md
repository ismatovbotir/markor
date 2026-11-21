# /make-migration

Generate only the migration for a model based on CLAUDE.md schema.

## Usage
```
/make-migration <ModelName>
```

Reads the schema from CLAUDE.md section 3 for the given model and creates the migration with all columns, indexes, and foreign keys defined there.
