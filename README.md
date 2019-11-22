### Ucoz to Joomla data converter.

This converter will help users to move their site from Ucoz to Joomla.

Goals:

- [x] Converter for categories
- [x] Converter for users
- [ ] Converter for blogs
- [ ] Converter for loads
- [ ] Converter for news
- [ ] Converter for publications

**How to install**

Copy into `cli` folder.

**How to run converter**

Before run you need to point where you backup folder is. To do this, open `config.ini` file and change `backupPath` to you path.

`php path_to_joomla/cli/ucoz_converter/categories.php` - this will create new categories in Joomla database.

If you want to create categories again, delete `categories_import.json` in `/imports` folder. You can do the same for other types(e.g. news, blogs, users).

**Import users**

Before starting import users create all needed additional fields for user in Joomla. After that fill in file `userfields.json` with proper field ids.
Default file `userfields.json` from repo filled with IDs listed in screenshot below.

![Image of user fields](https://raw.githubusercontent.com/Globulopolis/ucoz_converter/master/docs/Users%20Fields%20-%20Test%20-%20Administration%20for%20userfields.png)

![Image of user fields](https://raw.githubusercontent.com/Globulopolis/ucoz_converter/master/docs/userfields_json.jpg)

**Beware!!**

Some content(users, categories or news) cannot be moved as 1:1. At least for users Ucoz does not validate email, Joomla does.
