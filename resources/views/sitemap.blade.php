<urlset
    xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

<url>

    <loc>{{ url('/') }}</loc>

    <lastmod>{{ now()->toDateString() }}</lastmod>

    <changefreq>daily</changefreq>

    <priority>1.0</priority>

</url>



@foreach($categories as $category)

<url>

    <loc>
    {{ url('/category/'.$category->slug) }}
    </loc>

    <lastmod>
    {{ $category->updated_at->toDateString() }}
    </lastmod>

    <changefreq>weekly</changefreq>

    <priority>0.8</priority>

</url>

@endforeach




@foreach($products as $product)

<url>

    <loc>
        {{ url('/product-details/'.$product->slug) }}
    </loc>


    @if($product->images && $product->images->count())

    <image:image>

        <image:loc>
            {{ $product->images->first()->url }}
        </image:loc>

    </image:image>

    @endif


    <lastmod>
        {{ $product->updated_at->toDateString() }}
    </lastmod>


    <changefreq>
        weekly
    </changefreq>


    <priority>
        0.7
    </priority>


</url>

@endforeach


</urlset>
