<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function getCountries(): JsonResponse
    {
        $countries = [
            'GB' => ['name' => 'United Kingdom', 'code' => 'GB', 'cities' => ['London', 'Manchester', 'Birmingham', 'Leeds', 'Glasgow', 'Liverpool', 'Newcastle', 'Sheffield']],
            'ES' => ['name' => 'Spain', 'code' => 'ES', 'cities' => ['Madrid', 'Barcelona', 'Valencia', 'Seville', 'Bilbao', 'Malaga', 'Murcia', 'Palma']],
            'US' => ['name' => 'United States', 'code' => 'US', 'cities' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego']],
            'FR' => ['name' => 'France', 'code' => 'FR', 'cities' => ['Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice', 'Nantes', 'Strasbourg', 'Montpellier']],
            'DE' => ['name' => 'Germany', 'code' => 'DE', 'cities' => ['Berlin', 'Munich', 'Cologne', 'Frankfurt', 'Hamburg', 'Dusseldorf', 'Dortmund', 'Essen']],
            'IT' => ['name' => 'Italy', 'code' => 'IT', 'cities' => ['Rome', 'Milan', 'Naples', 'Turin', 'Palermo', 'Genoa', 'Bologna', 'Florence']],
            'CA' => ['name' => 'Canada', 'code' => 'CA', 'cities' => ['Toronto', 'Montreal', 'Vancouver', 'Calgary', 'Edmonton', 'Ottawa', 'Winnipeg', 'Quebec City']],
            'AU' => ['name' => 'Australia', 'code' => 'AU', 'cities' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Gold Coast', 'Canberra', 'Newcastle']],
            'JP' => ['name' => 'Japan', 'code' => 'JP', 'cities' => ['Tokyo', 'Yokohama', 'Osaka', 'Kobe', 'Kyoto', 'Kawasaki', 'Saitama', 'Hiroshima']],
            'CN' => ['name' => 'China', 'code' => 'CN', 'cities' => ['Beijing', 'Shanghai', 'Guangzhou', 'Shenzhen', 'Chengdu', 'Hangzhou', 'Wuhan', 'Xi\'an']],
            'IN' => ['name' => 'India', 'code' => 'IN', 'cities' => ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad']],
            'BR' => ['name' => 'Brazil', 'code' => 'BR', 'cities' => ['São Paulo', 'Rio de Janeiro', 'Brasília', 'Salvador', 'Fortaleza', 'Belo Horizonte', 'Manaus', 'Curitiba']],
            'MX' => ['name' => 'Mexico', 'code' => 'MX', 'cities' => ['Mexico City', 'Guadalajara', 'Monterrey', 'Cancún', 'Playa del Carmen', 'Acapulco', 'Veracruz', 'Merida']],
            'ZA' => ['name' => 'South Africa', 'code' => 'ZA', 'cities' => ['Johannesburg', 'Cape Town', 'Durban', 'Pretoria', 'Port Elizabeth', 'Bloemfontein', 'Pietermaritzburg', 'East London']],
            'SG' => ['name' => 'Singapore', 'code' => 'SG', 'cities' => ['Singapore']],
            'AE' => ['name' => 'United Arab Emirates', 'code' => 'AE', 'cities' => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah', 'Fujairah', 'Umm Al Quwain']],
            'NZ' => ['name' => 'New Zealand', 'code' => 'NZ', 'cities' => ['Auckland', 'Wellington', 'Christchurch', 'Hamilton', 'Tauranga', 'Lower Hutt', 'Dunedin', 'Palmerston North']],
            'TH' => ['name' => 'Thailand', 'code' => 'TH', 'cities' => ['Bangkok', 'Chiang Mai', 'Phuket', 'Pattaya', 'Krabi', 'Rayong', 'Nakhon Ratchasima', 'Udon Thani']],
            'MY' => ['name' => 'Malaysia', 'code' => 'MY', 'cities' => ['Kuala Lumpur', 'George Town', 'Johor Bahru', 'Petaling Jaya', 'Subang Jaya', 'Klang', 'Selangor', 'Penang']],
        ];

        return response()->json($countries);
    }

    public function getCities(string $countryCode): JsonResponse
    {
        $countries = [
            'GB' => ['London', 'Manchester', 'Birmingham', 'Leeds', 'Glasgow', 'Liverpool', 'Newcastle', 'Sheffield'],
            'ES' => ['Madrid', 'Barcelona', 'Valencia', 'Seville', 'Bilbao', 'Malaga', 'Murcia', 'Palma'],
            'US' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego'],
            'FR' => ['Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice', 'Nantes', 'Strasbourg', 'Montpellier'],
            'DE' => ['Berlin', 'Munich', 'Cologne', 'Frankfurt', 'Hamburg', 'Dusseldorf', 'Dortmund', 'Essen'],
            'IT' => ['Rome', 'Milan', 'Naples', 'Turin', 'Palermo', 'Genoa', 'Bologna', 'Florence'],
            'CA' => ['Toronto', 'Montreal', 'Vancouver', 'Calgary', 'Edmonton', 'Ottawa', 'Winnipeg', 'Quebec City'],
            'AU' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Gold Coast', 'Canberra', 'Newcastle'],
            'JP' => ['Tokyo', 'Yokohama', 'Osaka', 'Kobe', 'Kyoto', 'Kawasaki', 'Saitama', 'Hiroshima'],
            'CN' => ['Beijing', 'Shanghai', 'Guangzhou', 'Shenzhen', 'Chengdu', 'Hangzhou', 'Wuhan', 'Xi\'an'],
            'IN' => ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad'],
            'BR' => ['São Paulo', 'Rio de Janeiro', 'Brasília', 'Salvador', 'Fortaleza', 'Belo Horizonte', 'Manaus', 'Curitiba'],
            'MX' => ['Mexico City', 'Guadalajara', 'Monterrey', 'Cancún', 'Playa del Carmen', 'Acapulco', 'Veracruz', 'Merida'],
            'ZA' => ['Johannesburg', 'Cape Town', 'Durban', 'Pretoria', 'Port Elizabeth', 'Bloemfontein', 'Pietermaritzburg', 'East London'],
            'SG' => ['Singapore'],
            'AE' => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah', 'Fujairah', 'Umm Al Quwain'],
            'NZ' => ['Auckland', 'Wellington', 'Christchurch', 'Hamilton', 'Tauranga', 'Lower Hutt', 'Dunedin', 'Palmerston North'],
            'TH' => ['Bangkok', 'Chiang Mai', 'Phuket', 'Pattaya', 'Krabi', 'Rayong', 'Nakhon Ratchasima', 'Udon Thani'],
            'MY' => ['Kuala Lumpur', 'George Town', 'Johor Bahru', 'Petaling Jaya', 'Subang Jaya', 'Klang', 'Selangor', 'Penang'],
        ];

        $cities = $countries[strtoupper($countryCode)] ?? [];

        return response()->json($cities);
    }
}
