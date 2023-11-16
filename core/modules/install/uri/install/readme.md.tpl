Installation 
============

**Install Nimbly Core**<br />
[set repos-name="nimbly-[slug [site-name]]"]
Clone the nimbly core into '[repos-name]':<br />

```
cd ~/work (or replace with your project root dir)
git clone git@gitlab.com:Nimbly-Web-Systems-firma/nimbly-core.git [repos-name]
cd [repos-name]
```

**Clone scaleup repos into ext**<br />
Clone the [repos-name] repos into 'ext':<br />

```
git clone git@github.com:Nimbly-Web-Systems/[repos-name].git ext
```

**Create and run docker image**<br />
```
cd docker && docker-compose up -d && cd ..
```

Install required modules and build css/js files
-----------------------------------------------
```
npm install
npm run build
```


Installation Script
-------------------
The first time you run the source, go to [http://localhost/install.php](http://localhost/install.php) to make a super user account and setup the directory structure. This needs to be done only once. 


