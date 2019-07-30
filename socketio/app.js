/**
 * Module dependencies.
 */

var express = require('express')
        , routes = require('./routes')
        , http = require('http');

var app = express();
var server = app.listen(3000);
var hostName = '52.8.251.143';
var io = require('socket.io').listen(server); // this tells socket.io to use our express server

app.configure(function () {
//  app.set('views', __dirname + '/views');
  app.set('view engine', 'jade');
  app.use(express.favicon());
  app.use(express.logger('dev'));
  app.use(express.static(__dirname + '/public'));
  app.use(express.bodyParser());
  app.use(express.methodOverride());
  app.use(app.router);
});

app.configure('development', function () {
  app.use(express.errorHandler());
});

app.post('/emit', function (req, res) {
  var recieverId = req.body.receiver_id;
  var roomId = recieverId;
  io.sockets["in"](roomId).emit('newmessagearrived', req.body);
  res.json(req.body);
});

app.get('/', routes.index);

var getUrlParameter = function getUrlParameter(sParam, url) {
  var sPageURL = decodeURIComponent(url),
          sURLVariables = url.split('&'),
          sParameterName,
          i;

  for (i = 0; i < sURLVariables.length; i++) {
    sParameterName = sURLVariables[i].split('=');

    if (sParameterName[0] === sParam) {
      return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
    }
  }
};

io.sockets.on('connection', function (socket) {
  var loginUser = "";
  console.log(socket.handshake);
  var token = getUrlParameter('token', socket.handshake.url);
  var options = {
    "hostname": hostName,
    /*"port": "3000",*/
    "path": "/php-ravel-backend/index.php/ravelmessage/getLoginUserId/",
    "method": "POST",
    "headers": {
      "Content-Type": "application/json",
      "token": token
    }
  };
  
  console.log('token'+token);
  
  var reqToken = http.request(options, function (res) {
    res.setEncoding('utf8');
    res.on('data', function (body) {
      loginUser = body;
      if (loginUser) {
        var roomId = loginUser;
        if (roomId) {
          socket.join(roomId);
          var options = {
            "hostname": hostName,
            /*"port": "3000",*/
            "path": "/php-ravel-backend/index.php/ravelmessage/userSocketConnect/",
            "method": "POST",
            "headers": {
              "Content-Type": "application/json",
              "token": token
            }
          };

          var req = http.request(options, function (res) {
            res.setEncoding('utf8');
            res.on('data', function (body) {
              console.log('Connect: ' + body);
            });
          });
          req.end();

          socket.on('disconnect', function () {
            var options = {
              "hostname": hostName,
              /*"port": "3000",*/
              "path": "/php-ravel-backend/index.php/ravelmessage/userSocketDisConnect/",
              "method": "POST",
              "headers": {
                "Content-Type": "application/json",
                "token": token
              }
            };

            var reqDisconnect = http.request(options, function (res) {
              res.setEncoding('utf8');
              res.on('data', function (body) {
                console.log('Disconnect: ' + body);
                console.log('disconnectLoginUser: ' + roomId);
              });
            });
            reqDisconnect.end();
          });
        }
      }
    });
  });
  reqToken.end();
});
	