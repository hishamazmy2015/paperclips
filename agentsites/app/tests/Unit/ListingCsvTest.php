<?php

declare(strict_types=1);

use App\Listings\ListingCsv;

it('provides a template with the documented columns', function (): void {
    $lines = explode("\n", trim(ListingCsv::template()));
    expect($lines)->toHaveCount(2)->and($lines[0])->toBe(implode(',', ListingCsv::COLUMNS));
});

it('normalises a row into listing attributes and reports what is wrong', function (): void {
    $ok = ListingCsv::normalize(['ref' => ' FP-1001 ', 'title_en' => 'Villa', 'offering' => 'For Sale', 'property_type' => 'Villa', 'price' => '7,900,000', 'currency' => 'aed', 'bedrooms' => '4', 'bathrooms' => '', 'area_sqft' => '4200', 'community' => 'Dubai Hills', 'city' => 'Dubai', 'status' => '', 'featured' => 'yes', 'media' => 'https://a.example/1.jpg; https://a.example/2.jpg']);
    expect($ok['errors'])->toBe([])
        ->and($ok['attributes'])->toMatchArray(['ref' => 'FP-1001', 'offering' => 'sale', 'property_type' => 'villa', 'price' => 7900000.0, 'currency' => 'AED', 'bedrooms' => 4, 'bathrooms' => null, 'area_sqft' => 4200.0, 'status' => 'available', 'featured' => true, 'title_ar' => null])
        ->and($ok['media'])->toBe(['https://a.example/1.jpg', 'https://a.example/2.jpg']);

    expect(ListingCsv::normalize(['ref' => 'R-1', 'title_en' => 'Flat', 'offering' => 'lease', 'property_type' => 'studio', 'price' => '72000'])['attributes']['offering'])->toBe('rent');

    $bad = ListingCsv::normalize(['ref' => '', 'title_en' => '', 'offering' => 'swap', 'property_type' => 'castle', 'price' => '0', 'currency' => 'dirhams', 'bedrooms' => '99', 'status' => 'gone', 'media' => 'ftp://x']);
    expect($bad['errors'])->toHaveCount(9)
        ->and(implode(' ', $bad['errors']))->toContain('ref:')->toContain('title_en:')->toContain('offering:')->toContain('property_type:')->toContain('price:')->toContain('currency:')->toContain('bedrooms:')->toContain('status:')->toContain('media:');
    expect(ListingCsv::normalize(['ref' => 'R-1', 'title_en' => 'x', 'offering' => 'sale', 'property_type' => 'villa', 'price' => '1', 'media' => 'ftp://x'])['errors'][0])->toContain('media');
});

it('reads rows by header (BOM tolerant) and refuses a file without the required columns', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "\xEF\xBB\xBFRef,Title_EN,offering,property_type,price,extra\nA-1,One,sale,villa,100,ignored\n\n,,,,\nA-2,Two,rent,studio,50,\n");
    $rows = ListingCsv::rows($path);
    expect(array_keys($rows))->toBe([2, 5])
        ->and($rows[2])->toBe(['ref' => 'A-1', 'title_en' => 'One', 'offering' => 'sale', 'property_type' => 'villa', 'price' => '100'])
        ->and($rows[5]['ref'])->toBe('A-2');

    file_put_contents($path, "ref,title_en\nA-1,One\n");
    expect(fn () => ListingCsv::rows($path))->toThrow(InvalidArgumentException::class, 'missing columns');
    file_put_contents($path, '');
    expect(fn () => ListingCsv::rows($path))->toThrow(InvalidArgumentException::class);
    expect(fn () => ListingCsv::rows('/nowhere/none.csv'))->toThrow(InvalidArgumentException::class);
});
