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

**Beware!!**

Some content(users, categories or news) cannot be moved as 1:1. At least for users Ucoz does not validate email, Joomla does.
