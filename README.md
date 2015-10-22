# RawMVC

This is a tiny, but powerful PHP MVC framework. It's very minimal in size, but allows for systems to be very easily built. It was designed for use as creating a tiny standalone, drop-in that could run inside or alongside an existing framework or CMS, such as WordPress.

## Installation

The system must be installed manually, but it's a simple three-step process.

1. Set up your DB's connection and specify the connection details in db-config.php (copy that from db-config.example.php).
2. Update any necessary constants in the inc/const.php file.
3. Install the models to the DB: in the package's root directory run Linux shell script: `./install`
