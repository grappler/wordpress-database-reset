WordPress Database Reset
------------------------

A plugin that allows you to skip the 5 minute installation and reset WordPress's database back to its original state.

###Features

* Extremely fast one click process to reset the WordPress database
* Choose to reset the entire database or specific database tables
* Secure and super simple to use
* Prefer the command line? Reset the database in one command
* Excellent for theme and plugin developers who need to clean the database of any unnecessary content quickly

###Command Line
Once activated, you can use the WordPress Database Reset plugin with [WordPress CLI][1].

#####Reset the database
```php
wp reset database
```
Want to specify a list of tables to reset?
```php
wp reset database --tables='users, posts, comments, options'
```
The current theme and plugins will be reactivated by default. You can disable them like so:
```php
wp reset database --no-reactivate
```

#####List the table names
Use the list command if you're unsure of the database table names:
```php
wp reset list
```

###Contributing

Contribute by sending in a plugin suggestion, an improvement or a pull request. If you are having an issue with the plugin, you can file an issue on the GitHub [issue page][2] or create a post on the WordPress [support forum][3].

###Credits

[bsmSelect][4] - Better Select Multiple (vicb | Ryan Cramer)

[1]: http://wp-cli.org/
[2]: https://github.com/chrisberthe/wordpress-database-reset/issues
[3]: https://wordpress.org/support/plugin/wordpress-database-reset
[4]: https://github.com/vicb/bsmSelect
