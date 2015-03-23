# Documentation

## Install:

* Download
  - via ZIP/tarball available in the GitHub page
  - via Git `git clone https://github.com/leolweb/cfs-translatable.git`
* Install
  - uncompress the tarball in /wp-content/plugins/
* Activate
  - as plug-in directly in WordPress Admin > Plug-ins > activate "CFS Translatable"
  - as embed in functions.php, example: `require WP_PLUGIN_DIR . "/cfs-translatable/index.php"


## Usage:

Define configuration constants directly in the WordPress configuration file (usually wp-config.php).

### with gettext (faster)

It uses gettext directly (it parses all fields in this way __( $field, $textdomain ) ), so it's faster than light but more hard to use and mantains.
You could generate a pot file to translate or a php file, so you can use the "msgmerge" tool or add the generated php file to gettext findable path(s).

* to generate strings in a php file:

```php
define( 'CFS_TRANSLATABLE_TEXTDOMAIN', 'your_textdomain' );
define( 'CFS_TRANSLATABLE_GENERATE_PHP', true );
```

* to generate a pot file:

```php
define( 'CFS_TRANSLATABLE_TEXTDOMAIN', 'your_textdomain' );
define( 'CFS_TRANSLATABLE_GENERATE_POT', true );
```

### with shortcodes (slower)

It uses preg_match_all() and array_combine(), in the same way that WordPress to manages shortcodes, it's slow due to these functions but so easy to maintains, you could change direcly CFS fields without external files to translate.

```php
define( 'CFS_TRANSLATABLE_SHORTCODES', true );
```

in CFS fields use shortcodes like these:

* ISO 639-1
`[en]Custom field[/en][it]Campo personalizzato[/it][fr]Entrée personnalisé[/fr][es]Campo personalizado[/es][de]etc[/de][ru]...[/ru]`

* ISO 639-1 with ISO 3166 (hyphenated)
`[en-us]Custom field[/en-us][it-IT]Campo personalizzato[/it-IT]`

* with fallback (missing translations)

```php
define( 'CFS_TRANSLATABLE_DEFAULT_LANGUAGE', 'it' );
```
`[it]Campo personalizzato[/it] - or - [it-IT]Campo personalizzato[/it-IT]`


## Constants

CFS_TRANSLATABLE_SHORTCODESdescription: Enable shortcode support
type: boolean
default: false


CFS_TRANSLATABLE_TEXTDOMAIN
description: Pass textdomain to use in gettext Fn
type: string
default: ''

CFS_TRANSLATABLE_DEFAULT_LANGUAGE
description: Default translation language for shortcode and fallback
type: string
default: 'en'

CFS_TRANSLATABLE_GENERATE_POT
description: Enable pot generation (keep disabled in production environment)
type: boolean
default: false 

CFS_TRANSLATABLE_GENERATE_PHP
description: Enable php generation (keep disabled in production environment)
type: boolean
default: false

CFS_TRANSLATABLE_POT_LANGUAGE
description: Default translation language, saved in pot
type: string
default: 'en_US'

CFS_TRANSLATABLE_PATH
description: Path where to save files
type: string
default: __DIR__

CFS_TRANSLATABLE_FILENAME
description: Filename for both pot and php files
type: string
default: 'cfs_translatable'


## Function hooks

### Actions

'cfs_translatable_generate_pot'
description: Alter generated pot code
function: generate_pot()

'cfs_translatable_generate_php'
description: Alter generated php code
function: generate_php()

'cfs_translatable_save_file'
description: Alter file saving
function: save_file()


### Filters

'cfs_translatable_export_options'
description: Manipulate options passed to cfs_field_group->export()
param: array $options
function: get_fields()

'cfs_translatable_parse_fields'
description: Manipulate $field_groups before parse
param: array $fields_group
function: get_fields()

'cfs_translatable_sanatize_fields'
description: Sanatize $field_groups after parse
param: array $fields
function: parse_fields()

'cfs_translatable_translate_metabox_title'
description: Translate CFS metabox title
param: array $matches
function: translate_metabox_title()

'cfs_translatable_translate_input_fields'
description: Translate CFS fields
param: array $fields
function: translate_input_fields()

'cfs_translatable_shortcode'
description: The (non-wp-core) shortcode function
param: string $text
function: shortcode()

'cfs_translatable_pot_header'
description: Add custom pot header
param: string $pot_header
function: get_pot_header()
