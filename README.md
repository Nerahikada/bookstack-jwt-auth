# bookstack-jwt-auth
A theme to use JWT auth with [BookStack](https://github.com/BookStackApp/BookStack).

## How to use
1. Download this repository and install dependencies
```bash
cd YOUR_BOOKSTACK_DIR/themes
git clone https://github.com/Nerahikada/bookstack-jwt-auth.git
cd bookstack-jwt-auth
composer install
```

2. Edit lines 5 to 7 of `functions.php`.

3. Add `APP_THEME=bookstack-jwt-auth` to the environment variable and start BookStack.
