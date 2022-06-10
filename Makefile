VERSION ?= 2.0.4-naifei

image:
	docker build . -t ushuz/dujiaoka:$(VERSION)
	docker push ushuz/dujiaoka:$(VERSION)
