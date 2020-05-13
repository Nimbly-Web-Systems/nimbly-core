Installation 
============

**Install Nimbly Core**<br />
[set repos-name="nimbly-[slug [site-name]]"]
Clone the nimbly core into '[repos-name]':<br />

```
cd ~/work (or replace with your project root dir)
git clone git@gitlab.com:volst-firma/nimbly.git [repos-name]
cd [repos-name]
```

**Clone scaleup repos into Ext**<br />
Clone the [repos-name] repos into 'ext':<br />

```
git clone git@github.com:Volst/[repos-name].git ext
```

**Create and run docker image**<br />
```
cd misc
docker-compose up
````

**Installation script**<br />
Setup the directory structure and create a super user account by opening  [http://localhost/](http://localhost/). This needs to be done only once. 