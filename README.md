# Plugin Gitlab-Project

Plugin Gitlab-Project display a Gitlab project inside Dokuwiki.

## Requirements

This plugin does not need any requirements.

## Install

Download Gitlab Project into your `${dokuwiki_root}/lib/plugins` folder and restart dokuwiki or use the Extension Manager.

## Configuration

You can configure Gitlab-Project in the Configuration Manager of Dokuwiki:

* **server.default**: Set your default Gitlab url, without slash ending. You can override this setting in `server.json` file.
* **token.default**: Fill your admin token. You can override this setting in `server.json` file.
* **unwanted.users**: If you want to not display some users of your project, add them here, separated by commas.

Gitlab-Project will use this data by default.

## Syntax

### Default Syntax:

```php
<gitlab project="<NAMESPACE>/<PROJECT_NAME>" />
```

**NAMESPACE** is the namespace of your project, typically the name of the user or group in which the project is located.

**PROJECT_NAME** is the name of project.

For e.g., if you have a project available at `http://my-gitlab/foo/bar`, the syntax will be:

```php
<gitlab project="foo/bar" />
```

### Override Server and Token

Inside the root of the plugin, you will have a JSON file called: `server.json`. Inside you can add other servers and their tokens, than the one defined in the plugin settings.

Just call it after by its name.

E.g.:

Say that you've the following json file:

```json
{
    "second": {
        "url": "http://my-second-gitlab.com",
        "api_token": "a1a1a1a1a1a11a1a"
    }
    "third": {
        "url": "http://my-third-gitlab.com",
        "api_token": "b2b2b2b2b2b222b2"
    }
}
```

Then simply add `server` parameter:

```php
<gitlab server="second" project="foo/bar" />
```
