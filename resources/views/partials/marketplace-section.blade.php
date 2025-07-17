<div 
    id="marketplace-app"
    data-listings="{{ json_encode($featuredListings) }}"
    data-total="{{ $totalListings }}"
    data-has-more="{{ $hasMorePages ? 'true' : 'false' }}"
></div>