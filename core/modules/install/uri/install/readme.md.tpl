Installation 
============

**Install Nimbly Core**<br />
[module format][set repos-name="nimbly-[slug [site-name]]"]
```
cd ~/dev (or replace with your project developlment root dir)
git clone git@gitlab.com:volst-firma/nimbly.git [repos-name]
cd [repos-name]
```
**Clone [repos-name] into Ext**<br />
```
git clone git@github.com:Volst/[repos-name].git ext
```
**Create and run docker image**<br />
```
cd misc
docker-compose up
```
**Installation script**<br />
Setup the directory structure and create a super user account by opening [http://localhost/](http://localhost/). This needs to be done only once. 