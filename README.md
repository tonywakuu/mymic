CodeIgniter Library:

Required configuration :

1- PHP 5.5
2- apache server
3- Mongo
4- Curl

Step to setup code base
1. Pull code base form git in /var/www directory. if the default apache directory is /var/www/html then pull code here.
2. Change base url path in application/config/config.php for the variable $config['base_url'].(like localhost/name of the folder or localip/name of the folder).
3. Set path for mongo db for variable $config['mongo'] in config file.

4. change path for base_url,app_base_url in "/var/www/php-ravel-backend/php-ravel/config/providerConfig.php"

// plan collections

db.plan.insert({"name" : "FREE","price" : "0.00","blength" : NumberLong(600),"bnum" : NumberLong(5),"cnum" : NumberLong(1),"sbcast" : NumberLong(0),"btype" : NumberLong(0),"st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810),"default":NumberLong(1)})

db.plan.insert({"name" : "Entertainment","price" : "0.99","blength" : NumberLong(900),"bnum" : NumberLong(5),"cnum" : NumberLong(2),"sbcast" : NumberLong(0),"btype" : NumberLong(0),"st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.plan.insert({"name" : "Choice","price" : "1.99","blength" : NumberLong(1200),"bnum" : NumberLong(10),"cnum" : NumberLong(3),"sbcast" : NumberLong(1),"btype" : NumberLong(0),"st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.plan.insert({"name" : "Extra","price" : "2.99","blength" : NumberLong(1800),"bnum" : NumberLong(10),"cnum" : NumberLong(5),"sbcast" : NumberLong(1),"btype" : NumberLong(1),"st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.plan.insert({"name" : "Ultimate","price" : "3.99","blength" : NumberLong(3600),"bnum" : NumberLong(15),"cnum" : NumberLong(10),"sbcast" : NumberLong(1),"btype" : NumberLong(1),"st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.plan.insert({"name" : "Premier","price" : "4.99","blength" : NumberLong(0),"bnum" : NumberLong(20),"cnum" : NumberLong(15),"sbcast" : NumberLong(1),"btype" : NumberLong(1),"st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})



// ratingmaster collections

db.ratingmaster.insert({"name" : "G","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})
db.ratingmaster.insert({"name" : "PG","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})
db.ratingmaster.insert({"name" : "PG-13","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})
db.ratingmaster.insert({"name" : "R","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})
db.ratingmaster.insert({"name" : "NC-17","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})


// channelcategory collections

db.channelcategory.insert({"uid" : "","name" : "Travel & Events","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Sports","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Science & Technology","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Pets & Animals","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "People & Blogs","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Nonprofits & Activism","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "News & Politics","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Music","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Howto & Style","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Gaming","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Film & Animation","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Education","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Comedy","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

db.channelcategory.insert({"uid" : "","name" : "Autos and Vehicles","st" : NumberLong(1),"cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})

//Collection for message used in report
db.reportmsg.insert({"msg":"Harassment and Cyberbullying","cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})
db.reportmsg.insert({"msg":"Impersonation","cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})
db.reportmsg.insert({"msg":"Violent Threats","cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})
db.reportmsg.insert({"msg":"Child Endangerment","cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})
db.reportmsg.insert({"msg":"Hate Speech Against a Protected Group","cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})
db.reportmsg.insert({"msg":"Spam and Scams","cat" : NumberLong(1455892810),"mat" : NumberLong(1455892810)})