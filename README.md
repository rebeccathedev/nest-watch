# Maintainer Wanted

October 2019: I no longer use Nest thermostats, so I have no need for this code
anymore and will not be actively maintaining it. If you are interested in taking
over, [comment here](https://github.com/peckrob/nest-watch/issues/6).

# Nest Watch

Nest Watch is a small command-line script that polls your Nest thermostats and
stuffs the results into InfluxDB. From there you can use an analytics platform
such as Grafana to build reports.

## Why?

I have two Nest thermostats in my house and, after some teething pains (yay the
life of an early adopter) they have been pretty solid. But they're also black
boxes that I know little about. I know they're collecting mountains of data and
sending it back to the Google mothership. Wouldn't it be nice to get at some of
that data and build my own reports?

The more practical reason, however, is that I have a house that is heated and
cooled by electric heat pumps, with heat strips as an auxiliary backup. Those
things pull **50 amps** when they are in use. I might as well light dollar bills
on fire. So anything that I can do to reduce my use of aux heating I try to do.

Unfortunately, Nest's reports are delayed by a day so I can't make realtime
adjustments to my usage. Furthermore, Nest's reports don't give me any data more
granular than "(heat|cool) was used (in 15 minute increments) today". You can
look at a timeline and kinda see where it was used, but that's it.

This may be fine for a normal person. But I'm an engineer, not a normal
person. :) I want data!

So when I came across [this unofficial API](https://github.com/gboudreau/nest-api),
I instantly thought of how neat it would be to use it to grab that data and do
something with it. Rather than write my own analytics on top of it, I decided
instead to just stuff the data into a time-series database (InfluxDB), off which
I could use any platform I wanted to build reports or alerting.

## Warning

**This is a wholly unofficial project.** It might stop working at any time. Do
not rely on this for life-critical stuff. I am not responsible for what you do
with it.

## Installing

Prerequisites: Needs InfluxDB, PHP, Composer and probably some other stuff.

Clone the repo:

```
git clone git@github.com:peckrob/nest-watch.git /opt/nest-watch
cd /opt/nest-watch
chmod +x nestwatch
```

Install packages:

```
cd src
composer install
```

Configure source:

```
cp nestwatch.conf.sample /etc/nestwatch.conf
vi /etc/nestwatch.conf
```

Fill in your Nest username and password (yes, that sucks, but this is an
unofficial API after all). Also fill in the InfluxDB information. Then run it
and see if you get data:

```
/opt/nest-watch/nestwatch -v
```

If everything is good and you have data going into InfluxDB, cron it up. I have
mine running once every five minutes, mostly because I really don't have any
idea of how often Nests report back to Google and, anyways, I don't want to
hammer the API. I figure 5 minutes is good enough resolution for my purposes.

```
crontab -e
*/5 * * * * /opt/nest-watch/nestwatch
```

## Nest Target Mode

```
0 = Off
1 = Range (Cool & Heat)
2 = Cool
3 = Heat
```

## License

GPLv3
