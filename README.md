## John Blakey Private Tours

This is the contents for the following website:

[http://dev.privateguidedtours.london](http://dev.privateguidedtours.london)


### Dependencies

#### Docker

The site is setup using docker, if you're on on OSX grab a copy of boot2docker
with brew:

```bash
$: brew install boot2docker
```

then the following to get it setup:

```bash
$: boot2docker init
$: boot2docker up
```

export the environment variables it gives you in `.bashrc` or whatever other shell
you're using.

#### Fig

Fig is used to wire up the containers and manage their lifecycle, run the following
to install it:

```bash
$: brew install fig
```
### Running the site

#### Database

First get the latest copy of the site then create a folder called `db_backups` and
put the database dump in there under the name of `wordpress.sql`.

> If the dump command hasn't added the following make sure it's in there:

```bash
CREATE DATABASE wordpress;
USE wordpress;
```

#### Containers

To start the containers up simply run:

```bash
$: fig up -d
```

This will pull down all the containers, start and link them up using the inital backup
in the `db_backups` folder.

One the command has finished, run:

```bash
boot2docker ip
```

and enter that into the browser, and the site should load.
