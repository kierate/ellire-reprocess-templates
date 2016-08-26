# Ellire - deployment-specific configuration management

* [Introduction](https://github.com/kierate/ellire-reprocess-templates/#introduction)
 * [Templates](https://github.com/kierate/ellire-reprocess-templates/#templates)
 * [Macros](https://github.com/kierate/ellire-reprocess-templates/#macros)
 * [Profile](https://github.com/kierate/ellire-reprocess-templates/#profile)
* [Macro configuration](https://github.com/kierate/ellire-reprocess-templates/#macro-configuration)
 * [Different configuration files](https://github.com/kierate/ellire-reprocess-templates/#different-configuration-files)
 * [Environment variables](https://github.com/kierate/ellire-reprocess-templates/#environment-variables)
 * [Macro overrides](https://github.com/kierate/ellire-reprocess-templates/#macro-overrides)
* [Installation](https://github.com/kierate/ellire-reprocess-templates/#installation)
* [Running](https://github.com/kierate/ellire-reprocess-templates/#running)
* [Other Commands](https://github.com/kierate/ellire-reprocess-templates/#other-commands)
* [License](https://github.com/kierate/ellire-reprocess-templates/#license)
* [Acknowledgments](https://github.com/kierate/ellire-reprocess-templates/#acknowledgments)

## Introduction

Ellire simplifies deployment-specific configuration for your applications.

You provide "macro" configuration for all different "profiles" your applications run in (e.g. dev, test, staging, prod) and with that Ellire can process all "template" files where macros are used to generate the final files relevant for the environment/deployment.

These 3 main concepts are covered in depth in the sections below.

### Templates

A template file is any file in your application's codebase with the extension as defined in the `dist_file_extension` macro (`template` by default). Such files will be processed by Ellire and it will generate the non-template files (if you want to exclude certain directories from processing then provide them as a comma-separated list paths relative to the root of your project in the `template_exclude_paths` macro).
Any files in your application that change depending on the server/environment they are deployed to should be made into templates.

Let have a look at a simple example. Imagine you have this file in your codebase under `app/config/config.php`:

```php
<?php
return [
    //...
    'domain' => 'www.example.com',
    'enable_debug' => false,
    'send_emails' => true,
    'mail_from' => 'hello@example.com',
    //...
];
```
When running this application locally for example, you will have a different domain, you will want to enable debug mode and maybe stop sending emails out altogether or change the sender email.

To achieve this with Ellire you move your file to `app/config/config.php.template` (or a different extension if you prefer by changing `dist_file_extension` as mentioned earlier) and injecting macros into the new template file:

```
<?php
return [
    //...
    'domain' => '@app_domain@',
    'enable_debug' => @enable_debug@,
    'send_emails' => @send_emails@,
    'mail_from' => '@mail_from_address@',
    //...
];
```
That's it. The template file is created. Now let's have a look at macros - what they are and where they come from.

### Macros

Within Ellire a macro is a piece of config that has a name and a value. Macro values can depend (i.e. contain) other macro values. They are defined primarily in configuration files, but can also be provided via command line parameters and environment variables.

Continuing the example from the previous section imagine you have an `ellire.json` file in you application with the following content...:

```json
{
    "globals": {
        "enable_debug": false,
        "port": "",
        "mail_from_address": "hello@example.com"
    },
    "dev": {
        "app_domain": "dev.example.com",
        "enable_debug": true,
        "send_emails": false,
        "port": "8888"
    },
    "prod": {
        "app_domain": "www.example.com",
        "send_emails": true
    }
}
```
...your `app/config/config.php.template` will be processed and Ellire will generate `app/config/config.php` with the following values when using the `dev` profile...:

```php
<?php
return [
    //...
    'domain' => 'dev.example.com',
    'enable_debug' => true,
    'send_emails' => false,
    'mail_from' => 'hello@example.com',
    //...
];
```

...and the the following values when using the `prod` profile:

```php
<?php
return [
    //...
    'domain' => 'www.example.com',
    'enable_debug' => false,
    'send_emails' => true,
    'mail_from' => 'hello@example.com',
    //...
];
```

The process of defining and resolving the macro values has a little more to it, so be sure to check out the [macro configuration section](https://github.com/kierate/ellire-reprocess-templates/#macro-configuration).

This last example talks about the profile. This term is explained in the section below.

### Profile
A profile in Ellire is essentially an identifier for the environment that your application is running in e.g. dev, staging, prod etc.

A profile is just an arbitrary string, so you can use anything that your prefer or that suits your application framwork (e.g. prod vs. production vs. live). The one exception to that is `globals` which has a special meaning - it indicates configuration that applies to all profiles.

Macro values in the system, user and application configuration files are grouped by profile. This means you can pre-define and manage the configuration for all your servers in one file and when running Ellire it will resolve the correct macro values for the profile that applies to the current server you are on.

See the [macro configuration section](https://github.com/kierate/ellire-reprocess-templates/#macro-configuration) for more details on how the profile is resolved and used.

## Macro configuration

Ellire resolves macro values based on the following:
* a system config file (`/etc/ellire.json`)
* a user config file (`$HOME/.ellire/ellire.json`)
* a local (application-specifc) config file which comes from your verison control system (e.g. `ellire.json`)
* an instance-specific config file (e.g. `.ellire-instance.json`)
* environment variables
* manual macro override values passed in to `ellire reprocess-templates`

*Note: the `profile`, `deploy_path` and `config_extension` macro values are resolved first based on the `globals` section of the system and user config file, then environment variables and macro overrides. If you provide their values anywhere else these will be ignored.*
 
See below for details on all of these.
 
### Different configuration files

#### System-wide

The system configuration file defines macro values for all users of the machine. This file is therfore particularly useful if you will be using more than 1 user account on the machine where Ellire is installed.

The system configuration file is in JSON format and needs to be placed in `/etc/ellire.json`. It can contain default macro values for all profiles (the `globals` section) as well as configuration for each profile your application can run in.

Example:

```json
{
    "globals": {
        "profile": "dev",
        "config_extension": "json",
        "dist_file_extension": "template",
        "generated_files_writable": "false",
        "macro_opening_string": "@",
        "macro_closing_string": "@",
        "template_exclude_paths": "src/, tests/, vendor/",
        "app_domain": "dev.example.com",
        "app_env": "@profile@",
        "url": "https://@app_domain@@port@",
        "enable_debug": false,
        "send_emails": false,
        "port": ""
    },
    "dev": {
        "enable_debug": true
    },
    "prod": {
        "app_domain": "www.example.com",
        "send_emails": true
    }
}
```

#### User-specific

The user configuration file defines macro values for the current user only. It is in JSON format and needs to be placed in `$HOME/.ellire/ellire.json`. It can contain default macro values for all profiles (the `globals` section) as well as configuration for each profile your application can run in.

Example:

```json
{
    "globals": {
        "cache_path": "/home/myuser/custom/cache/path"
    },
    "dev": {
        "port": "8888"
    }
}
```

#### Application-specific

The application specific file is the main configuration file - it is different per each application that you write. It should sit in the root of your project/application and be committed/checked-in to your version control system.

The file name for it is always `ellire` however the extension that best suits you/your project can be configured via the `config_extension` macro (the possible options are `json`, `yml`, `xml` or `ini`) in the system config file or the user config file. It can contain default macro values for all profiles (the `globals` section) as well as configuration for each profile your application can run in.

Example:

```json
{
    "globals": {
        "mail_from": "foo@dev.example.com",
        "ssl_enabled": false,
        "template_exclude_paths": "src/, tests/, app/, node_modules",
    },
    "dev": {
        "db_host": "localhost",
        "db_user": "root",
        "db_pass": "",
    },
    "prod": {
        "mail_from": "user@example.com",
        "ssl_enabled": true,
        "db_host": "db.example.com",
        "db_user": "superapp"
    }
}
```

#### Instance-specific

The instance file allows you to provide different macro values for the same application deployed on the same machine (e.g. multiple test/development versions of the same application running in parallel on the same machine). This file should never be committed/checked-in to your version control system.

The file name for it is always `.ellire-instance` however the extension that best suits you/your project can be configured via the `config_extension` macro (the possible options are `json`, `yml`, `xml` or `ini`) in the system config file or the user config file. It can only contain configuration for the current profile your application is running in.

Example:

```json
{
    "port": "8889",
    "app_debug": false,
    "ssl_enabled": true
}
```

### Environment variables

Ellire will check the environment for values of any macros that it is aware of (i.e. you have defined them in the configuration files, or used them in any of the template files).

Macro values are read from variables with the `ELLIRE_` prefix. For example:
* macro named `foo` can be set with the `ELLIRE_FOO` variable
* macro named `5%off` can be set with the `ELLIRE_5_OFF` variable (non alphanumeric chars are replaced with an undererscore)

### Macro overrides

Manual overriding of any macro value is also possible. This is done by passing in `-m` or `--macro` to the `ellire reprocess-templates` command.
Each macro passed in has to be in a `macroName=macroValue` format e.g.

```bash
ellire reprocess-templates --macro macro1=foo -m macro2=bar
```

## Installation

Install Ellire globally with [composer](https://getcomposer.org/download/):

```bash
composer global require ellire/reprocess-templates
```

### Create system/user configuration files

Once Ellire is installed you need to set up macro configuration specific for your machine/user account. To get you started on that the default config can be installed with an Ellire command:

```bash
ellire install-default-config
```

*Note: Installing the system config file requires a privileged user. Ellire will tell you if the installation fails
 and offer options for manual installation of the file.*

## Running

Running Ellire is very easy:

```bash
ellire reprocess-templates
```

This will show you which template files were changed and which ones remained the same (were skipped).

To get more details about what Ellire is doing while running this you can enable the verbose more:

```bash
ellire reprocess-templates -v
```

or the debug mode:

```bash
ellire reprocess-templates -vvv
```

*Note: in this mode all resolved macros (including any sensitive data you set like passwords, API keys etc.) will be dispayed on STDOUT.*

Also as mentiod [above](https://github.com/kierate/ellire-reprocess-templates/#macro-overrides) you can provide individual macro values when running ellire:

```bash
ellire reprocess-templates --macro macro1=foo -m macro2=bar
```

## Other commands

Aside from the main command to reprocess templates Ellire also provides the following:

```
ellire list-macros
```
to list all resolved and fully processed macros (templates are not changed) and 
```
ellire get-profile
```
to show the profile used for the current deployment only (again templates will not be changed).

## License

Ellire is licensed under the MIT License. For details see the [LICENSE](LICENSE) file.

## Acknowledgments

This project is based on the brilliant (but now abandoned) [WADF](https://github.com/timjackson/wadf) by Tim Jackson.

