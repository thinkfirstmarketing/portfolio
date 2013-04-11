"""
    Our main development platform is called Django. It's a python-based web-framework that is widely used. It offers a great deal of helpful tools to speed up development, and helps to discipline the programmer to follow good MVC standards. Its object-relationship management tool is also very good, and allows for great, database-independent structure. 
    This project, which you can view at http://www.domeoproducts.com/ipad-cases/ is a landing page for Domeo's iPad case products. The following is from the controller that presents a page to the user to show them the available products. 
"""
#Other methods and libraries ommitted 

def products_landing(request):

    #We like to put power to modify SEO elements in the hands of our SEO experts at Think First Marketing. We create objects in the database that represent the static content on individual pages. Then, our SEO experts (who don't know how to code) can log into the back-end and update SEO elements like meta tags, header tags, titles, among other things. 
    staticpages = StaticPage.objects.order_by('ordering')

    #go to the database and get a list of products 
    products = Product.objects.all()

    #build the rating for the product from the information saved in the database for each category of iPad product 
    for product in products:
        tally = [-1,0,0,0,0,0]
        for review in product.productrating_set.all():
            review.full_stars = range(review.overall_rating)
            review.empty_stars = range(5-review.overall_rating)
            tally[review.overall_rating] += 1
        total = 0
        count = 0
        for i in range(1, 6):
            total += tally[i] * i
            count += tally[i]

        product.has_ratings = total > 0

        #if the product has a rating, do some math to make it fit into a rating of 1-5
        if product.has_ratings:
            product.average_rating = total / (count * 1.0)
            product.average_rating = round(product.average_rating * 2) / 2

            product.full_stars = range(int(product.average_rating))
            product.half_star = bool(int(product.average_rating * 2) % 2)
            if product.half_star:
                left = 4
            else:
                left = 5
            product.empty_stars = range(left-int(product.average_rating))

    #return render a new page that has all of the information that's currently in scope
    return render(request, 'products-landing.html', locals())