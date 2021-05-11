# Api manager

This drupal 8 & 9 module can be used to synchronize (add, update and remove automatically) JSON feeds without having to write any code.

All you have to do is map the fields or the origin with its destination via the interface.

![API manager drupal 8](https://i.imgur.com/qEYfy3H.jpg)

### Features

- Synchronize items to <b>nodes</b> or <b>tags</b>.
- Possibility to <b>cross-link</b> items with entity reference.
- <b>Available mapping fields</b>: title, created/updated, textfield, textarea, image, entity reference, location, datefield, integer.
- Set your own <b>intervals</b>: some API's can run on daily basis, other on hourly or minutes.
- Per API-call a <strong>trigger</strong> link is provided to trigger a call externally.
- Error logging

#### Not included

- Do not use this module if you would like to sync tens of thousands of items. It is meant for <b>small to medium-sized</b> datasets (e.g. up to 10.000). Nevertheless, you could give it a try.
- Authorisation. Deliberately we did <b>not include any type of authorisation</b>. This means server needs direct access to the JSON url. So if your data is behind some kind of security wall: create a custom controller to make your JSON available and let Api Manager handle the synchronisation.
- Although it is possible to choose target language, <b>importing translations of a node are not possible</b>. Though it is possible to cross-link with entity reference.

#### Future roadmap

- We are planning to add a paging functionality as well,so feeds can be fully read (e.g. ?page=1, ?page=2). This is not included yet.
- A more extended logging overview.

## Getting started

### Installation
Download and install this module as a zip file, or with composer and place it in your custom modules directory.

With composer, add the following to your repositories:
```
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "vitobelgium/api-manager",
                "version": "master",
                "type":"drupal-module",
                "source": {
                    "url": "https://github.com/VITObelgium/api-manager.git",
                    "type": "git",
                    "reference": "master"
                }
            }
        }
     ]
```
Then run the following
```composer require vitobelgium/api-manager```.

Enable with drush:```drush en api_manager```

Or with drupal console: ```drupal module:install api_manager```

This will create an entity called ```api```. An overview page is available at ```admin/api_manager/list```, a menu link is provided under "Structure". Here you can create your first Api connection.

### Settings documentation

Label | Description | Value
--- | --- | ---
Label | The name of your API | ```string```<br>f.e. "Events"
API url | The url of your feed | ```string```<br> Must be accessible by the server
User id  | Id of the user who will be creator of the content | ```integer``` <br>Must be an id of a registered drupal user
The content type or taxonomy to the API will create items for. | Choose the destination bundle of your feed (node or taxonomy) | ```string``` <br>Choose a value from the select list.
Unique map field | The textfield on the destination content type where the api can store the unique id from the API items. | ```string``` <br>f.e. 'field_sync_id'. Its value after importing could for example become ``event_sync_845``
Api object: unique id identifier | The id key or unique identifier of your import items. f.e. 'id', 'item_id', 'item_uuid'. The key should be available on each item of your import items.<br>If your json has items like ``{"item_id":845, ...}`` "item_id" would be this value. It is needed to synchronize the node/term and the json item. | ```string``` <br>f.e. 'item_id'
Api object: updated date identifier | The updated time identifier of your import items. f.e. "updated", "updated_at". If your json is structured like ``{"item_id":845,"updated_at":2020-03-11T21:58:26.050+01:00"}``, then "updated_at" would be this value. | ```string``` <br>f.e. 'updated_at'
Api object: parent item identifier (optional, for taxonomy only) | If you import hierarchical terms, here you can specify parent id of a term. | ```string``` f.e. 'parent_id'
The destination language of the nodes or terms | Choose the destination entity: conten type or taxonomy bundle |  ```string``` <br>Choose a value from the select list.
Interval | The minimum amount of minutes between every sync. Set to 0 to import every cron run. Set to "-" if you do not want to trigger the import on cron and thus only on external trigger. | ```integer``` (to enable) ```string "-"``` (to disable) <br>Amount of minutes between every sync (depending on interval of your cronjob)
Weight | The order in which the API should run. f.e. always give terms a lower weights because nodes can refer to them. | ```integer``` <br>Lower weight means earlier run
Active | Is this import active or not | ```boolean``` <br>If disabled, items will never get imported.

### Mapping fields

Label | Description | Required
--- | --- | ---
Textfield| Map your fields like this: api_item_key&#124;content_type_field, f.e. name&#124;title, organisation_name&#124;field_organisation_name. use 'title' for nodes and 'name' for terms.|Yes
Textarea|Map your fields like this: api_item_key&#124;content_type_field, f.e. organisation_description&#124;body, oranisation_description&#124;field_organisation_description.|No
Image|Map your images like this: api_item_key&#124;content_type_field, f.e. organisation_logo&#124;field_organisation_logo.|No
Image root url|If the api uses relative urls like "/uploads/image.jpg, you can specify the root here f.e. "https://mysite.com".|No|
Entity reference|Map your references. These should point to IDs of other api items. Make sure you respect the order of import. <br>Setup: api_item_key[entity_machine_name]&#124;content_type_field, f.e. organisation_id[organisation]&#124;field_organisation_reference. Both machine names for taxonomy or node are allowed.|No
List item|Map your list items like this: api_item_keycontent_type_field f.e.field_organisation_type.| No
Integer|Map your numbers like this: api_item_key&#124;content_type_field, f.e. organisation_id&#124;field_organisation_id.|No
Datefield|Map your fields like this: api_item_key#124;content_type_field, f.e. event_date#124;field_event_date. The sync will identify the type of date.| No
Geolocation|Map your fields like this: lat_key+long_key&#124;content_type_field, f.e. location.lat+location.lng&#124;field_location_geolocation.| No

### Questions

Use the GitHub issue queue to create an issue. You can directly contact our maintainer Stef at [stef.vanlooveren@vito.be](mailto:stefvanlooveren@vito.be).
