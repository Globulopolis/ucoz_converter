### Ucoz to Joomla data converter (WIP).

This converter will help users to move their site from Ucoz to Joomla.

Goals:

- [x] Converter for categories
- [x] Converter for users
- [x] Converter for blogs
- [ ] Converter for loads
- [ ] Converter for news
- [ ] Converter for publications
- [x] Web configurator

**Requirements**
All requirements for Joomla.
PHP memory limit should set at least to 100Mb. And higher value required if you have big dump.

**How to install**

Copy into root Joomla folder.

**How to run converter**

Before run any converters, you need to go to `http://your_site/ucoz_converter/index.php` and set up all settings.

`php path_to_joomla/ucoz_converter/categories.php` - this will create new categories in Joomla database.

If you want to create categories again, delete `categories_import.json` in `/imports` folder. You can do the same for other types(e.g. news, blogs, users).

**Import users**

Before starting import users create all needed additional fields for user in Joomla. After that, enter the values in Extra fields on the Users and groups tab.

**NB!** If `#__fields_values` table have tons of records when update all fields require some time(~1 sec per user for 6 fields). The more fields, the more time for updating. If you do not want(or not required) update these fields set `Insert extra fields` option to `No`.

Run `php path_to_joomla/ucoz_converter/users.php` to import info about users and if import successful when run `php path_to_joomla/ucoz_converter/ugen.php` to update info about users(such as banned or not and user group).

**Beware!!**

Some content(users, categories or news) cannot be moved as 1:1. At least for users Ucoz does not validate email, Joomla does.
