Junk-Checker
============
A [GrumPHP](https://github.com/phpro/grumphp) extension to check you're not
committing junk in your commits.

Configuration
-------------
You just have to add in your grumphp config file the following:

```yaml
parameters:
    tasks:
        junk_checker:
            junks: [var_dump, dump]
            triggered_by:  [php]

    extensions:
        - GrumPHPJunkChecker\ExtensionLoader
```
