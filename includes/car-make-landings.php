<?php
/**
 * Managed car_make landing pages.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

function autoagora_get_car_make_landing_config() {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $build_url = static function ($slug) {
        return trailingslashit(home_url('/car_make/' . $slug));
    };

    $config = array(
        'mazda-cx-5' => array(
            'slug' => 'mazda-cx-5',
            'make_name' => 'Mazda',
            'make_slug' => 'mazda',
            'model_name' => 'CX-5',
            'model_slug' => 'mazda-cx-5',
            'title' => 'Mazda CX-5 for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Browse used Mazda CX-5 cars for sale in Cyprus. Compare prices, specs, and photos from dealers and private sellers across Nicosia, Limassol, Larnaca, and Paphos.',
            'h1' => 'Mazda CX-5 for Sale in Cyprus',
            'canonical' => $build_url('mazda-cx-5'),
            'intro' => array(
                "The Mazda CX-5 is one of the most sought-after compact SUVs in Cyprus. Known for its sharp handling, upscale interior, and Skyactiv engine technology, the CX-5 offers a driving experience that stands out in the crowded SUV segment. Mazda is the second most registered used car brand on the island, and the CX-5 is a big part of that - it appeals to buyers who want something that feels more premium without the price tag of a German SUV.",
                "On the used market in Cyprus, you'll find the CX-5 in both petrol and diesel variants, with the 2.0-litre and 2.5-litre petrol engines being the most common. Most examples available are right-hand drive models imported from Japan, which suits Cyprus's left-hand traffic system perfectly. Prices for a used Mazda CX-5 in Cyprus typically range from around EUR 15,000 for older models with higher mileage up to EUR 35,000 or more for recent low-mileage examples.",
                "Browse the listings below to find a Mazda CX-5 that fits your budget and preferences, or use the filters to narrow down by year, price, mileage, and fuel type.",
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used Mazda CX-5 cost in Cyprus?',
                    'answer' => 'Used Mazda CX-5 prices in Cyprus generally start from around EUR 15,000 for 2017-2018 models with higher mileage, while newer 2021-2023 models in good condition typically range from EUR 25,000 to EUR 35,000. Pricing depends on the year, mileage, engine type, and overall condition.',
                ),
                array(
                    'question' => 'Is the Mazda CX-5 a good car for driving in Cyprus?',
                    'answer' => "Yes. The CX-5's compact SUV size makes it well-suited to both city driving in Nicosia or Limassol and weekend trips to the Troodos mountains or coastal roads. Its relatively fuel-efficient engines help keep running costs manageable given fuel prices in Cyprus, and the higher driving position is practical for navigating the island's mix of highway and urban roads.",
                ),
                array(
                    'question' => 'Are Mazda CX-5 spare parts easy to find in Cyprus?',
                    'answer' => 'Mazda parts are widely available in Cyprus through both official channels and independent suppliers. Mazda is one of the top-selling brands on the island, so dealerships and independent mechanics are well-stocked. Many parts can also be sourced from Japan, which typically ships to Cyprus within a few weeks.',
                ),
                array(
                    'question' => 'Is the Mazda CX-5 available in right-hand drive?',
                    'answer' => "Yes. Since Cyprus drives on the left side of the road, right-hand drive vehicles are standard. Most Mazda CX-5 models available on the used market in Cyprus are right-hand drive, particularly those imported from Japan. You may also find some left-hand drive examples imported from Europe, but these are less common.",
                ),
            ),
        ),
        'mercedes-benz-a-class' => array(
            'slug' => 'mercedes-benz-a-class',
            'make_name' => 'Mercedes-Benz',
            'make_slug' => 'mercedes-benz',
            'model_name' => 'A-Class',
            'model_slug' => 'mercedes-benz-a-class',
            'title' => 'Mercedes-Benz A-Class for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Find used Mercedes-Benz A-Class cars for sale in Cyprus. Compare prices, specs, and photos from trusted sellers across Nicosia, Limassol, Larnaca, and Paphos.',
            'h1' => 'Mercedes-Benz A-Class for Sale in Cyprus',
            'canonical' => $build_url('mercedes-benz-a-class'),
            'intro' => array(
                'The Mercedes-Benz A-Class is one of the most popular premium hatchbacks in Cyprus, offering a blend of luxury and practicality that appeals to both young professionals and families. Mercedes is consistently among the top five car brands registered on the island, and the A-Class serves as an accessible entry point into the brand.',
                'Used A-Class models in Cyprus are available in a range of configurations, from the efficient A 180 to the sportier AMG-line variants. You will find both petrol and diesel options, with the A 180d and A 200 being the most common on the secondhand market. Prices for a used Mercedes A-Class in Cyprus typically start from around EUR 18,000 for pre-facelift W177 models and can reach EUR 40,000 or more for newer AMG-line editions with low mileage.',
                'Since Cyprus\'s new car market includes Mercedes through the Pilakoutas Group as the official distributor, servicing and parts availability are well-established across the island. Browse the A-Class listings below to find one within your budget.',
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used Mercedes-Benz A-Class cost in Cyprus?',
                    'answer' => 'Prices typically start from around EUR 18,000 for 2018-2019 models and go up to EUR 40,000 or more for recent model years with AMG styling packages and low mileage. Diesel variants tend to be slightly cheaper than petrol equivalents at the same age and mileage.',
                ),
                array(
                    'question' => 'Is the Mercedes A-Class expensive to maintain in Cyprus?',
                    'answer' => 'Maintenance costs are higher than for Japanese brands like Toyota or Mazda, but not unreasonable for a premium car. Mercedes has official service centres in Cyprus through the Pilakoutas Group, and independent specialists familiar with Mercedes vehicles are available in all major cities. Budget for slightly higher costs on parts like brake pads, tyres, and scheduled services compared to non-premium brands.',
                ),
                array(
                    'question' => 'What engine options are available in the A-Class?',
                    'answer' => 'The most common used A-Class engines in Cyprus are the 1.3-litre turbocharged petrol units in the A 180 and A 200 and the 1.5-litre or 2.0-litre diesel units in the A 180d and A 200d. Higher-performance versions include the A 250 and the AMG A 35, which features a 2.0-litre turbocharged petrol engine with over 300 horsepower.',
                ),
            ),
        ),
        'volkswagen-tiguan' => array(
            'slug' => 'volkswagen-tiguan',
            'make_name' => 'Volkswagen',
            'make_slug' => 'volkswagen',
            'model_name' => 'Tiguan',
            'model_slug' => 'volkswagen-tiguan',
            'title' => 'Volkswagen Tiguan for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Browse used Volkswagen Tiguan cars for sale in Cyprus. Compare prices, photos, and specs from sellers across Nicosia, Limassol, Larnaca, and Paphos.',
            'h1' => 'Volkswagen Tiguan for Sale in Cyprus',
            'canonical' => $build_url('volkswagen-tiguan'),
            'intro' => array(
                "The Volkswagen Tiguan is a popular choice among compact SUV buyers in Cyprus, valued for its spacious interior, composed ride, and the reassurance of Volkswagen's build quality. VW saw a significant sales surge in Cyprus recently, climbing to become one of the top-selling new car brands on the island, and the Tiguan is one of the key models driving that growth.",
                'On the used market, you will find the Tiguan in both petrol and diesel configurations, with the 2.0-litre TDI diesel being especially popular for its combination of performance and fuel economy. Most used examples in Cyprus are left-hand drive models sourced from Europe, though right-hand drive variants do exist. Used Tiguan prices in Cyprus generally start from around EUR 14,000 for older second-generation AD1 models and go up to EUR 40,000 or more for newer, well-equipped examples.',
                'Volkswagen is officially distributed in Cyprus by Unicars Ltd, so service centres and parts are readily available. Use the filters below to find a Tiguan that matches your needs.',
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used Volkswagen Tiguan cost in Cyprus?',
                    'answer' => 'Used Tiguan prices typically range from about EUR 14,000 for 2016-2018 models with higher mileage up to EUR 40,000 or more for late-model, low-mileage examples with premium trim levels. The R-Line and higher-spec versions command a premium.',
                ),
                array(
                    'question' => 'Is the Volkswagen Tiguan fuel-efficient?',
                    'answer' => 'The 2.0 TDI diesel variants are the most economical, typically returning around 6-7 litres per 100 km in mixed driving. The 1.4 and 1.5 TSI petrol engines are also reasonably efficient for an SUV of this size. Given fuel costs in Cyprus, the diesel variants tend to be more cost-effective for drivers covering longer distances.',
                ),
                array(
                    'question' => 'Are Volkswagen parts readily available in Cyprus?',
                    'answer' => 'Yes. Volkswagen has an established presence in Cyprus through its official distributor, Unicars Ltd. Authorised service centres operate across the main cities, and independent mechanics are well-versed in VW vehicles. Parts availability is generally not an issue, and European-sourced parts ship to Cyprus quickly since the country is an EU member state.',
                ),
            ),
        ),
        'mercedes-benz-gla' => array(
            'slug' => 'mercedes-benz-gla',
            'make_name' => 'Mercedes-Benz',
            'make_slug' => 'mercedes-benz',
            'model_name' => 'GLA',
            'model_slug' => 'mercedes-benz-gla',
            'title' => 'Mercedes-Benz GLA for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Find used Mercedes-Benz GLA cars for sale in Cyprus. Compare listings from dealers and private sellers across Nicosia, Limassol, Larnaca, and Paphos.',
            'h1' => 'Mercedes-Benz GLA for Sale in Cyprus',
            'canonical' => $build_url('mercedes-benz-gla'),
            'intro' => array(
                "The Mercedes-Benz GLA blends the compact dimensions of a hatchback with the raised ride height of an SUV, making it a practical and stylish option for Cyprus's urban roads and tight parking spaces. As a subcompact luxury crossover, it appeals to buyers who want the Mercedes badge and interior quality without stepping up to a larger, more expensive SUV.",
                'Used GLA models in Cyprus are commonly found in GLA 180, GLA 200, and GLA 200d variants. The second-generation GLA, from 2020 onwards, brought a more SUV-like design and improved interior space compared to the first generation. Prices on the used market in Cyprus generally start from around EUR 20,000 for first-generation models and climb to EUR 45,000 or more for newer second-generation examples with AMG-line styling.',
                'Mercedes-Benz vehicles in Cyprus are serviced through the Pilakoutas Group, ensuring access to authorised workshops and genuine parts. Browse the GLA listings below and use the filters to refine by year, price, and specifications.',
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used Mercedes GLA cost in Cyprus?',
                    'answer' => 'First-generation GLA models from 2014-2019 typically start from around EUR 20,000, while second-generation models from 2020 onwards range from about EUR 30,000 to EUR 45,000 depending on trim, mileage, and condition.',
                ),
                array(
                    'question' => "What's the difference between the GLA and the A-Class?",
                    'answer' => 'The GLA is essentially a raised, crossover version of the A-Class. It shares the same engines and much of the same technology, but offers a higher seating position, slightly more ground clearance, and a more SUV-oriented design. The GLA boot space is also a bit more practical for daily use.',
                ),
                array(
                    'question' => 'Is the GLA good for city driving in Cyprus?',
                    'answer' => 'Very much so. Its compact footprint makes it easy to manoeuvre through busy areas in Nicosia and Limassol, while the raised ride height gives you better visibility in traffic. The smaller engine options such as the GLA 180 and GLA 200 are well-suited to urban driving conditions.',
                ),
            ),
        ),
        'volkswagen-golf' => array(
            'slug' => 'volkswagen-golf',
            'make_name' => 'Volkswagen',
            'make_slug' => 'volkswagen',
            'model_name' => 'Golf',
            'model_slug' => 'volkswagen-golf',
            'title' => 'Volkswagen Golf for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Browse used Volkswagen Golf cars for sale in Cyprus. Find GTI, R-Line, and standard models from sellers across Nicosia, Limassol, Larnaca, and Paphos.',
            'h1' => 'Volkswagen Golf for Sale in Cyprus',
            'canonical' => $build_url('volkswagen-golf'),
            'intro' => array(
                'The Volkswagen Golf has been a staple on European roads for decades, and Cyprus is no exception. Known for its refined driving dynamics, practical hatchback shape, and strong residual values, the Golf remains a popular choice for buyers looking for a well-rounded daily driver. The sportier GTI and R variants have a dedicated following among driving enthusiasts on the island.',
                'Used Volkswagen Golf models in Cyprus span multiple generations, from the MK7 to the current MK8. Engine choices include the 1.0, 1.2, 1.4, and 1.5-litre TSI petrol units, the 1.6 and 2.0 TDI diesels, as well as the 2.0 TSI found in GTI and R models. Prices start from around EUR 7,000 for older MK7 models and go up to EUR 35,000 or more for newer GTI and R variants.',
                'VW is well-supported in Cyprus through Unicars Ltd. Use the filters below to narrow down your search by model year, engine type, and budget.',
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used Volkswagen Golf cost in Cyprus?',
                    'answer' => 'Standard Golf models typically range from EUR 7,000 for older MK7s with higher mileage up to about EUR 25,000 for newer MK8 models. GTI variants tend to start around EUR 18,000, while the Golf R commands EUR 30,000 and above depending on age and condition.',
                ),
                array(
                    'question' => 'Is the Golf GTI popular in Cyprus?',
                    'answer' => "Yes. The GTI has a strong following in Cyprus, particularly among younger buyers and car enthusiasts. It offers a balance of everyday practicality and spirited performance that's hard to beat at its price point. The GTI also holds its value well on the local used market.",
                ),
                array(
                    'question' => 'What should I watch out for when buying a used Golf?',
                    'answer' => 'On older TSI petrol models, particularly the 1.4 TSI in MK7, check for timing chain wear. On DSG gearbox models, ask about service history because the DSG requires a fluid change at regular intervals. Diesel models should have a clear DPF history, as replacements can be costly.',
                ),
            ),
        ),
        'bmw-x5' => array(
            'slug' => 'bmw-x5',
            'make_name' => 'BMW',
            'make_slug' => 'bmw',
            'model_name' => 'X5',
            'model_slug' => 'bmw-x5',
            'title' => 'BMW X5 for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Find used BMW X5 cars for sale in Cyprus. Compare prices, specs, and photos from dealers and private sellers across Nicosia, Limassol, Larnaca, and Paphos.',
            'h1' => 'BMW X5 for Sale in Cyprus',
            'canonical' => $build_url('bmw-x5'),
            'intro' => array(
                'The BMW X5 is a popular luxury SUV choice in Cyprus, combining strong performance with a premium interior and commanding road presence. BMW consistently ranks among the top five car brands on the island, and the X5 is one of the most desirable models in the lineup, particularly for buyers looking for a spacious, feature-rich family vehicle or a capable long-distance cruiser.',
                'Used BMW X5 models in Cyprus typically come in the xDrive25d, xDrive30d, and xDrive40i variants, with the diesel options being especially popular for their blend of torque and fuel economy. The G05 generation, from 2018 onwards, introduced a significant step up in technology and interior quality over the previous F15. Used X5 prices in Cyprus generally range from around EUR 25,000 for F15-generation models to EUR 70,000 or more for recent G05 examples with M Sport packages.',
                'BMW is officially distributed in Cyprus by the Pilakoutas Group, so authorised service and parts supply is well-established. Browse the X5 listings below to find one that matches your requirements.',
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used BMW X5 cost in Cyprus?',
                    'answer' => 'Prices vary significantly by generation and spec. F15-generation models from 2013-2018 typically start from around EUR 25,000, while the newer G05 from 2018 onwards ranges from about EUR 45,000 to EUR 70,000 or more. M Sport and M50i or M50d variants command a significant premium.',
                ),
                array(
                    'question' => 'Is the BMW X5 expensive to run in Cyprus?',
                    'answer' => 'Running costs are higher than average due to premium fuel, higher insurance brackets, and more expensive parts and servicing. The diesel variants such as the xDrive25d and xDrive30d are more economical on fuel than the petrol options. Annual road tax in Cyprus is based on CO2 emissions, and larger-engined X5 variants may attract higher road tax charges.',
                ),
                array(
                    'question' => 'Should I buy a petrol or diesel BMW X5?',
                    'answer' => "For most buyers in Cyprus, the diesel makes more sense for everyday use. The xDrive30d offers strong performance with better fuel economy than the petrol equivalents. However, if you primarily drive shorter city distances, a petrol or plug-in hybrid variant might suit you better, and you'd benefit from Cyprus's lower tax rates on low-emission vehicles.",
                ),
            ),
        ),
        'mazda-cx-30' => array(
            'slug' => 'mazda-cx-30',
            'make_name' => 'Mazda',
            'make_slug' => 'mazda',
            'model_name' => 'CX-30',
            'model_slug' => 'mazda-cx-30',
            'title' => 'Mazda CX-30 for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Browse used Mazda CX-30 cars for sale in Cyprus. Compare prices, specs, and photos from sellers across Nicosia, Limassol, Larnaca, and Paphos.',
            'h1' => 'Mazda CX-30 for Sale in Cyprus',
            'canonical' => $build_url('mazda-cx-30'),
            'intro' => array(
                "The Mazda CX-30 sits between the smaller CX-3 and the larger CX-5 in Mazda's SUV lineup, offering a balance of compact dimensions and a surprisingly upscale interior. It's a strong seller in Cyprus, where Mazda is the second most popular brand for used car registrations. The CX-30 appeals to buyers who want something slightly larger than a hatchback but do not need a full-sized SUV.",
                "Most used CX-30 models available in Cyprus are powered by the 2.0-litre Skyactiv-G petrol engine, with some examples featuring Mazda's mild-hybrid M Hybrid system for slightly improved fuel efficiency. Being a relatively new model, introduced in 2019, the CX-30 is predominantly found as a right-hand drive import from Japan on the Cyprus used market. Used prices typically start from around EUR 18,000 and go up to EUR 28,000 for newer, low-mileage examples.",
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used Mazda CX-30 cost in Cyprus?',
                    'answer' => 'Used CX-30 prices in Cyprus generally range from about EUR 18,000 for 2020 models to around EUR 28,000 for 2023-2024 examples with low mileage. The model is still relatively new, so the used supply is smaller compared to the CX-5 or CX-3.',
                ),
                array(
                    'question' => "What's the difference between the Mazda CX-30 and CX-5?",
                    'answer' => 'The CX-30 is smaller and based on the Mazda 3 platform, while the CX-5 is a class above with more rear passenger space and a larger boot. The CX-30 is better suited to city driving and tighter parking in urban Cyprus, while the CX-5 is the better choice for families needing more space.',
                ),
                array(
                    'question' => 'Is the Mazda CX-30 fuel-efficient?',
                    'answer' => 'Yes. The 2.0-litre Skyactiv-G engine with mild hybrid technology typically returns around 6.5-7.5 litres per 100 km in mixed driving conditions, which is competitive for a compact SUV. This makes it a cost-effective option given fuel prices in Cyprus.',
                ),
            ),
        ),
        'nissan-qashqai' => array(
            'slug' => 'nissan-qashqai',
            'make_name' => 'Nissan',
            'make_slug' => 'nissan',
            'model_name' => 'Qashqai',
            'model_slug' => 'nissan-qashqai',
            'title' => 'Nissan Qashqai for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Find used Nissan Qashqai cars for sale in Cyprus. Browse listings from dealers and private sellers across Nicosia, Limassol, Larnaca, and Paphos.',
            'h1' => 'Nissan Qashqai for Sale in Cyprus',
            'canonical' => $build_url('nissan-qashqai'),
            'intro' => array(
                'The Nissan Qashqai is one of the original compact crossovers and remains a practical, good-value option on the Cyprus used car market. Nissan is the third most popular brand for used car registrations on the island, and the Qashqai is one of its core models, offering a comfortable ride, good visibility, and a reputation for reliability that makes it a sensible family choice.',
                'Used Qashqai models in Cyprus span the second generation, from 2014 to 2021, and the newer third generation from 2021 onwards. Common engine choices include the 1.2 and 1.3-litre turbocharged petrol units and the 1.5-litre dCi diesel. The newest generation also introduced Nissan e-POWER hybrid system. Prices for a used Qashqai in Cyprus typically start from around EUR 12,000 for older models and go up to EUR 30,000 or more for newer examples.',
                "Most Qashqai models available in Cyprus are right-hand drive imports from Japan or the UK, which is well-suited to Cyprus's left-hand traffic system. Browse the listings below and filter by year, price, or fuel type to find the right one.",
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used Nissan Qashqai cost in Cyprus?',
                    'answer' => 'Second-generation models from 2014-2021 typically range from EUR 12,000 to EUR 22,000, while the newer third-generation model from 2021 onwards starts from around EUR 25,000. The e-POWER hybrid variants tend to sit at the higher end of the range.',
                ),
                array(
                    'question' => 'Is the Nissan Qashqai reliable?',
                    'answer' => 'The Qashqai has a solid reputation for reliability. The second-generation model in particular is well-known for being a low-maintenance, dependable vehicle. Nissan parts are widely available in Cyprus, and the brand has a strong service network through both official dealers and independent workshops.',
                ),
                array(
                    'question' => 'What is Nissan e-POWER?',
                    'answer' => "e-POWER is Nissan's series hybrid system, available on the latest Qashqai. The wheels are driven entirely by an electric motor, while a small petrol engine acts as a generator to charge the battery. You do not need to plug it in. It refuels like a normal petrol car but offers electric-like driving smoothness and improved fuel efficiency, which is well-suited to Cyprus's mix of city and highway driving.",
                ),
            ),
        ),
        'toyota-corolla' => array(
            'slug' => 'toyota-corolla',
            'make_name' => 'Toyota',
            'make_slug' => 'toyota',
            'model_name' => 'Corolla',
            'model_slug' => 'toyota-corolla',
            'title' => 'Toyota Corolla for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Browse used Toyota Corolla cars for sale in Cyprus. Compare prices, specs, and photos from dealers and private sellers across all major cities.',
            'h1' => 'Toyota Corolla for Sale in Cyprus',
            'canonical' => $build_url('toyota-corolla'),
            'intro' => array(
                'The Toyota Corolla is one of the most recognisable and trusted nameplates in Cyprus. Toyota leads the island in used car registrations by a significant margin, and the Corolla, along with the Yaris, is at the heart of that dominance. Buyers choose the Corolla for its proven reliability, low running costs, and excellent resale value.',
                'The used market in Cyprus offers the Corolla in hatchback, saloon, and Touring Sports body styles. The current generation is available with a 1.2-litre turbo petrol engine or Toyota 1.8-litre and 2.0-litre hybrid powertrains. Older models typically come with the 1.4 D-4D diesel or 1.6 and 1.8 petrol engines. Prices for a used Corolla in Cyprus start from as low as EUR 8,000 for older models and range up to EUR 25,000 for newer hybrid variants.',
                "Most Toyota Corolla models in Cyprus are right-hand drive vehicles imported from Japan, well-suited to the island's left-hand traffic system. Toyota has the largest dealer and service network in Cyprus through Dickran Ouzounian and Co. Ltd, ensuring easy access to parts and maintenance.",
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used Toyota Corolla cost in Cyprus?',
                    'answer' => 'Prices range widely depending on age and spec. Older models from 2015-2017 can start from around EUR 8,000, while newer hybrid variants from 2020-2023 typically range from EUR 18,000 to EUR 25,000. The Corolla holds its value well in Cyprus, so pricing tends to be stable.',
                ),
                array(
                    'question' => 'Is the Toyota Corolla Hybrid worth it in Cyprus?',
                    'answer' => 'For many buyers, yes. Hybrid cars now account for over 44 percent of all car registrations in Cyprus, and the Corolla Hybrid is one of the most popular choices. It offers excellent fuel economy, typically around 4.5-5.5 litres per 100 km in mixed driving, low CO2 emissions which mean lower road tax, and the reliability Toyota hybrids are known for. Cyprus government incentives have also supported hybrid and electric vehicles through subsidies and reduced tax rates.',
                ),
                array(
                    'question' => 'Are Toyota parts easy to find in Cyprus?',
                    'answer' => 'Toyota has the strongest parts and service network of any brand in Cyprus. The official distributor, Dickran Ouzounian and Co. Ltd, operates across the island, and independent Toyota specialists are found in every major city. Parts are readily available both locally and through Japan imports. This is one of the key reasons Toyota remains the most popular car brand in Cyprus.',
                ),
            ),
        ),
        'mazda-cx-3' => array(
            'slug' => 'mazda-cx-3',
            'make_name' => 'Mazda',
            'make_slug' => 'mazda',
            'model_name' => 'CX-3',
            'model_slug' => 'mazda-cx-3',
            'title' => 'Mazda CX-3 for Sale in Cyprus - Browse Listings | AutoAgora',
            'meta_description' => 'Find used Mazda CX-3 cars for sale in Cyprus. Browse listings with prices, specs, and photos from sellers across Nicosia, Limassol, Larnaca, and Paphos.',
            'h1' => 'Mazda CX-3 for Sale in Cyprus',
            'canonical' => $build_url('mazda-cx-3'),
            'intro' => array(
                'The Mazda CX-3 is a subcompact crossover that punches above its weight in terms of interior quality and driving feel. In Cyprus, where Mazda ranks as the second most popular used car brand, the CX-3 is a common sight, especially among buyers looking for a small, fuel-efficient SUV that still feels well-built and enjoyable to drive.',
                "Used CX-3 models in Cyprus are predominantly right-hand drive imports from Japan, available with the 1.5-litre Skyactiv-D diesel or the 1.8-litre diesel, as well as the 2.0-litre Skyactiv-G petrol engine. The CX-3 compact footprint makes it ideal for city driving in Nicosia or Limassol, while still being comfortable enough for longer drives. Used prices in Cyprus generally range from EUR 13,000 to EUR 22,000 depending on the year, mileage, and specification.",
            ),
            'faqs' => array(
                array(
                    'question' => 'How much does a used Mazda CX-3 cost in Cyprus?',
                    'answer' => 'Prices typically start from around EUR 13,000 for 2017-2018 models and go up to about EUR 22,000 for 2022-2023 examples with low mileage. The diesel variants are often priced similarly to petrol equivalents, though the petrol models are slightly more common.',
                ),
                array(
                    'question' => 'Is the Mazda CX-3 good for city driving?',
                    'answer' => "Very much so. It's one of the smallest SUVs available, which makes it easy to park and manoeuvre in busy areas. The elevated seating position gives you good visibility in traffic without the bulk of a larger vehicle. It's a popular choice for city commuters in Cyprus.",
                ),
                array(
                    'question' => 'How does the CX-3 compare to the CX-5?',
                    'answer' => 'The CX-3 is significantly smaller, with less boot space and rear legroom. It is best suited to individuals or couples, while the CX-5 is the better option for families who need more space. The CX-3 makes up for it with lower purchase prices, better fuel economy, and easier city manoeuvrability.',
                ),
            ),
        ),
    );

    return $config;
}

function autoagora_get_car_make_landing($slug) {
    $config = autoagora_get_car_make_landing_config();

    return isset($config[$slug]) ? $config[$slug] : null;
}

function autoagora_get_managed_car_make_landing_term($term) {
    if (!$term || is_wp_error($term) || !isset($term->slug)) {
        return null;
    }

    return autoagora_get_car_make_landing($term->slug);
}

/**
 * Managed landing config only (the curated SEO pages). Returns null for other car_make terms.
 */
function autoagora_get_current_car_make_landing() {
    if (!is_tax('car_make')) {
        return null;
    }

    return autoagora_get_managed_car_make_landing_term(get_queried_object());
}

/**
 * Data for the car_make archive template: managed copy when configured, otherwise synthetic titles/meta and empty intro/FAQ.
 *
 * @return array|null Same shape as entries in autoagora_get_car_make_landing_config(), plus optional managed_copy bool.
 */
function autoagora_get_car_make_landing_view_context() {
    if (!is_tax('car_make')) {
        return null;
    }

    $term = get_queried_object();
    if (!$term || is_wp_error($term) || empty($term->slug)) {
        return null;
    }

    $managed = autoagora_get_car_make_landing($term->slug);
    if ($managed) {
        $managed['managed_copy'] = true;

        return $managed;
    }

    return autoagora_build_synthetic_car_make_landing_context($term);
}

/**
 * Default SEO + labels for car_make terms that are not in the managed landing config.
 *
 * @param WP_Term $term Queried car_make term.
 * @return array
 */
function autoagora_build_synthetic_car_make_landing_context($term) {
    $slug  = $term->slug;
    $name  = $term->name;
    $canonical = trailingslashit(home_url('/car_make/' . $slug));

    if ((int) $term->parent > 0) {
        $parent = get_term($term->parent, 'car_make');
        $make_name = ($parent && !is_wp_error($parent)) ? $parent->name : '';
        $make_slug = ($parent && !is_wp_error($parent)) ? $parent->slug : '';
        $model_name = $name;
        $model_slug = $slug;

        $h1 = trim($make_name . ' ' . $model_name) . ' ' . __('for Sale in Cyprus', 'bricks-child');
        $title = sprintf(
            /* translators: 1: make name, 2: model name */
            __('%1$s %2$s for Sale in Cyprus - Browse Listings | AutoAgora', 'bricks-child'),
            $make_name,
            $model_name
        );
        $meta_description = sprintf(
            /* translators: 1: make name, 2: model name */
            __('Browse used %1$s %2$s cars for sale in Cyprus. Compare prices, specs, and listings on AutoAgora.', 'bricks-child'),
            $make_name,
            $model_name
        );
    } else {
        $make_name   = $name;
        $make_slug   = $slug;
        $model_name  = '';
        $model_slug  = '';

        $h1 = sprintf(
            /* translators: %s: car make name */
            __('%s for Sale in Cyprus', 'bricks-child'),
            $make_name
        );
        $title = sprintf(
            /* translators: %s: car make name */
            __('%s for Sale in Cyprus - Browse Listings | AutoAgora', 'bricks-child'),
            $make_name
        );
        $meta_description = sprintf(
            /* translators: %s: car make name */
            __('Browse used %s cars for sale in Cyprus. Compare prices, specs, and listings on AutoAgora.', 'bricks-child'),
            $make_name
        );
    }

    return array(
        'slug'              => $slug,
        'make_name'         => $make_name,
        'make_slug'         => $make_slug,
        'model_name'        => $model_name,
        'model_slug'        => $model_slug,
        'title'             => $title,
        'meta_description'  => $meta_description,
        'h1'                => $h1,
        'canonical'         => $canonical,
        'intro'             => array(),
        'faqs'              => array(),
        'managed_copy'      => false,
    );
}

/**
 * Term IDs for listing queries on car_make landings (same rules as taxonomy-car_make-landing.php).
 *
 * @param array $landing View context from autoagora_get_car_make_landing_view_context().
 * @return int[]
 */
function autoagora_car_make_landing_resolve_tax_term_ids(array $landing) {
    $tax_terms = array();

    $model_slug = isset($landing['model_slug']) ? $landing['model_slug'] : '';
    if ($model_slug !== '') {
        $model_term = get_term_by('slug', $model_slug, 'car_make');
        if ($model_term && !is_wp_error($model_term)) {
            return array((int) $model_term->term_id);
        }
    }

    $make_slug = isset($landing['make_slug']) ? $landing['make_slug'] : '';
    if ($make_slug === '') {
        return array();
    }

    $make_term = get_term_by('slug', $make_slug, 'car_make');
    if (!$make_term || is_wp_error($make_term)) {
        return array();
    }

    $child_ids = get_terms(array(
        'taxonomy'   => 'car_make',
        'parent'     => $make_term->term_id,
        'hide_empty' => false,
        'fields'     => 'ids',
    ));

    if (!is_wp_error($child_ids) && !empty($child_ids)) {
        return array_map('intval', $child_ids);
    }

    return array((int) $make_term->term_id);
}

/**
 * Whether any published, not-sold cars exist for this landing scope.
 *
 * @param array $landing View context.
 */
function autoagora_car_make_landing_has_listings(array $landing) {
    $tax_terms = autoagora_car_make_landing_resolve_tax_term_ids($landing);
    if (empty($tax_terms)) {
        return false;
    }

    $query_args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => ListingStateManager::meta_query_exclude_sold(),
        'tax_query'      => array(
            array(
                'taxonomy'         => 'car_make',
                'field'            => 'term_id',
                'terms'            => $tax_terms,
                'include_children' => false,
            ),
        ),
    );

    if (function_exists('car_listings_execute_query')) {
        $q = car_listings_execute_query($query_args);
    } else {
        $q = new WP_Query($query_args);
    }

    return (int) $q->found_posts > 0;
}

/**
 * Empty car_make archives send users to the main cars browse experience.
 */
function autoagora_redirect_empty_car_make_archive() {
    if (!is_tax('car_make') || is_preview()) {
        return;
    }

    $landing = autoagora_get_car_make_landing_view_context();
    if (!$landing) {
        return;
    }

    if (!apply_filters('autoagora_redirect_empty_car_make_archive', true, $landing)) {
        return;
    }

    if (autoagora_car_make_landing_has_listings($landing)) {
        return;
    }

    wp_safe_redirect(trailingslashit(home_url('/cars/')), 302);
    exit;
}
add_action('template_redirect', 'autoagora_redirect_empty_car_make_archive', 5);

function autoagora_get_car_make_landing_sync_option_name() {
    return 'autoagora_car_make_landing_sync_state';
}

function autoagora_ensure_car_make_term($name, $slug, $parent = 0) {
    $term = get_term_by('slug', $slug, 'car_make');

    if (!$term && $parent > 0) {
        $matching_terms = get_terms(array(
            'taxonomy' => 'car_make',
            'hide_empty' => false,
            'parent' => (int) $parent,
            'name' => $name,
        ));

        if (!is_wp_error($matching_terms) && !empty($matching_terms)) {
            $term = $matching_terms[0];
        }
    } elseif (!$term) {
        $term = get_term_by('name', $name, 'car_make');
    }

    if (!$term || is_wp_error($term)) {
        $result = wp_insert_term($name, 'car_make', array(
            'slug' => $slug,
            'parent' => (int) $parent,
            'description' => $parent > 0 ? ($name . ' model landing term') : ('Car make: ' . $name),
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        return get_term($result['term_id'], 'car_make');
    }

    $update_args = array();

    if ($term->name !== $name) {
        $update_args['name'] = $name;
    }

    if ($term->slug !== $slug) {
        $update_args['slug'] = $slug;
    }

    if ((int) $term->parent !== (int) $parent) {
        $update_args['parent'] = (int) $parent;
    }

    if (!empty($update_args)) {
        $updated = wp_update_term($term->term_id, 'car_make', $update_args);

        if (is_wp_error($updated)) {
            return $updated;
        }

        $term = get_term($term->term_id, 'car_make');
    }

    return $term;
}

function autoagora_sync_car_make_landing_terms() {
    $config = autoagora_get_car_make_landing_config();
    $errors = array();

    foreach ($config as $landing) {
        $make_term = autoagora_ensure_car_make_term($landing['make_name'], $landing['make_slug'], 0);
        if (is_wp_error($make_term)) {
            $errors[] = $make_term->get_error_message();
            continue;
        }

        $model_term = autoagora_ensure_car_make_term($landing['model_name'], $landing['model_slug'], $make_term->term_id);
        if (is_wp_error($model_term)) {
            $errors[] = $model_term->get_error_message();
        }
    }

    return $errors;
}

function autoagora_car_make_landing_terms_are_intact() {
    foreach (autoagora_get_car_make_landing_config() as $landing) {
        $make_term = get_term_by('slug', $landing['make_slug'], 'car_make');
        $model_term = get_term_by('slug', $landing['model_slug'], 'car_make');

        if (!$make_term || is_wp_error($make_term) || (int) $make_term->parent !== 0) {
            return false;
        }

        if (
            !$model_term ||
            is_wp_error($model_term) ||
            (int) $model_term->parent !== (int) $make_term->term_id
        ) {
            return false;
        }
    }

    return true;
}

function autoagora_sync_car_make_landings_on_admin_init() {
    static $did_run = false;

    if ($did_run || !is_admin() || !current_user_can('manage_options') || !taxonomy_exists('car_make')) {
        return;
    }

    $did_run = true;

    $config_hash = md5(wp_json_encode(autoagora_get_car_make_landing_config()));
    $option_name = autoagora_get_car_make_landing_sync_option_name();
    $state = get_option($option_name, array());

    if (
        !empty($state['hash']) &&
        hash_equals($state['hash'], $config_hash) &&
        autoagora_car_make_landing_terms_are_intact()
    ) {
        return;
    }

    $errors = autoagora_sync_car_make_landing_terms();

    update_option($option_name, array(
        'hash' => $config_hash,
        'synced_at' => current_time('mysql'),
        'errors' => $errors,
    ));

    flush_rewrite_rules(false);
}
add_action('admin_init', 'autoagora_sync_car_make_landings_on_admin_init');

function autoagora_register_car_filter_query_vars($vars) {
    $vars[] = 'autoagora_car_filter_slug';

    return $vars;
}
add_filter('query_vars', 'autoagora_register_car_filter_query_vars');

function autoagora_register_car_filter_rewrite_rules() {
    add_rewrite_rule(
        '^cars/filter/make:([^/]+)/?$',
        'index.php?pagename=cars&autoagora_car_filter_slug=$matches[1]',
        'top'
    );
}
add_action('init', 'autoagora_register_car_filter_rewrite_rules');

function car_filters_parse_filter_url($filter_segment) {
    $filter_segment = sanitize_text_field((string) $filter_segment);

    if ($filter_segment === '' || strpos($filter_segment, 'make:') !== 0) {
        return array();
    }

    $slug = sanitize_title(substr($filter_segment, strlen('make:')));
    if ($slug === '') {
        return array();
    }

    $term = get_term_by('slug', $slug, 'car_make');
    if (!$term || is_wp_error($term)) {
        return array(
            'requested_slug' => $slug,
            'make' => '',
            'model' => '',
            'type' => '',
        );
    }

    if ((int) $term->parent > 0) {
        $parent_term = get_term($term->parent, 'car_make');

        return array(
            'requested_slug' => $slug,
            'make' => $parent_term && !is_wp_error($parent_term) ? $parent_term->slug : '',
            'model' => $term->slug,
            'type' => 'model',
        );
    }

    return array(
        'requested_slug' => $slug,
        'make' => $term->slug,
        'model' => '',
        'type' => 'make',
    );
}

function autoagora_get_active_car_filter_context() {
    static $context = null;

    if ($context !== null) {
        return $context;
    }

    $context = array(
        'is_filter_route' => false,
        'requested_slug' => '',
        'make_slug' => '',
        'model_slug' => '',
        'type' => '',
    );

    $pretty_slug = get_query_var('autoagora_car_filter_slug');
    if (!empty($pretty_slug)) {
        $resolved = car_filters_parse_filter_url('make:' . $pretty_slug);
        $context['is_filter_route'] = true;
        $context['requested_slug'] = !empty($resolved['requested_slug']) ? $resolved['requested_slug'] : sanitize_title($pretty_slug);
        $context['make_slug'] = !empty($resolved['make']) ? $resolved['make'] : '';
        $context['model_slug'] = !empty($resolved['model']) ? $resolved['model'] : '';
        $context['type'] = !empty($resolved['type']) ? $resolved['type'] : '';

        return $context;
    }

    if (!empty($_GET['model'])) {
        $model_slug = sanitize_title(wp_unslash($_GET['model']));
        $model_term = get_term_by('slug', $model_slug, 'car_make');
        if ($model_term && !is_wp_error($model_term)) {
            $context['model_slug'] = $model_slug;
            $context['type'] = 'model';

            if ((int) $model_term->parent > 0) {
                $parent_term = get_term($model_term->parent, 'car_make');
                if ($parent_term && !is_wp_error($parent_term)) {
                    $context['make_slug'] = $parent_term->slug;
                }
            }
        } elseif (!empty($_GET['make'])) {
            $context['make_slug'] = sanitize_title(wp_unslash($_GET['make']));
            $context['type'] = 'make';
        }
    } elseif (!empty($_GET['make'])) {
        $make_slug = sanitize_title(wp_unslash($_GET['make']));
        $context['make_slug'] = $make_slug;
        $context['type'] = 'make';
    }

    return $context;
}

function autoagora_is_cars_filter_route() {
    $context = autoagora_get_active_car_filter_context();

    return !empty($context['is_filter_route']);
}

function autoagora_get_current_request_url() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $home = home_url($request_uri);

    return esc_url_raw($home);
}

function autoagora_cars_filter_has_extra_filters() {
    foreach ($_GET as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }

        if (in_array($key, array('make', 'model'), true)) {
            continue;
        }

        return true;
    }

    return false;
}

function autoagora_get_cars_filter_canonical_url() {
    if (!autoagora_is_cars_filter_route()) {
        return '';
    }

    $context = autoagora_get_active_car_filter_context();
    if (!$context['requested_slug']) {
        return autoagora_get_current_request_url();
    }

    if (!autoagora_cars_filter_has_extra_filters()) {
        $landing = autoagora_get_car_make_landing($context['requested_slug']);
        if ($landing) {
            return $landing['canonical'];
        }
    }

    return autoagora_get_current_request_url();
}

function autoagora_get_current_faq_schema() {
    $landing = autoagora_get_current_car_make_landing();
    if (!$landing || empty($landing['faqs'])) {
        return '';
    }

    $entities = array();
    foreach ($landing['faqs'] as $faq) {
        $entities[] = array(
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text' => $faq['answer'],
            ),
        );
    }

    return wp_json_encode(array(
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $entities,
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function autoagora_get_current_seo_context() {
    if (is_tax('car_make')) {
        $ctx = autoagora_get_car_make_landing_view_context();
        if ($ctx) {
            return array(
                'type' => 'landing',
                'title' => $ctx['title'],
                'description' => $ctx['meta_description'],
                'canonical' => $ctx['canonical'],
                'robots' => 'index, follow',
            );
        }
    }

    if (autoagora_is_cars_filter_route()) {
        return array(
            'type' => 'cars_filter',
            'title' => '',
            'description' => '',
            'canonical' => autoagora_get_cars_filter_canonical_url(),
            'robots' => 'noindex, follow',
        );
    }

    return array();
}

function autoagora_filter_document_title_parts($parts) {
    $seo = autoagora_get_current_seo_context();
    if (!empty($seo['title'])) {
        $parts['title'] = $seo['title'];
    }

    return $parts;
}
add_filter('document_title_parts', 'autoagora_filter_document_title_parts');

function autoagora_filter_pre_get_document_title($title) {
    $seo = autoagora_get_current_seo_context();
    if (!empty($seo['title'])) {
        return $seo['title'];
    }

    return $title;
}
add_filter('pre_get_document_title', 'autoagora_filter_pre_get_document_title');

function autoagora_filter_wpseo_title($title) {
    $seo = autoagora_get_current_seo_context();

    return !empty($seo['title']) ? $seo['title'] : $title;
}
add_filter('wpseo_title', 'autoagora_filter_wpseo_title');

function autoagora_filter_wpseo_metadesc($description) {
    $seo = autoagora_get_current_seo_context();

    return !empty($seo['description']) ? $seo['description'] : $description;
}
add_filter('wpseo_metadesc', 'autoagora_filter_wpseo_metadesc');

function autoagora_filter_wpseo_canonical($canonical) {
    $seo = autoagora_get_current_seo_context();

    return !empty($seo['canonical']) ? $seo['canonical'] : $canonical;
}
add_filter('wpseo_canonical', 'autoagora_filter_wpseo_canonical');

function autoagora_filter_wpseo_robots($robots) {
    $seo = autoagora_get_current_seo_context();

    return !empty($seo['robots']) ? str_replace(' ', '', $seo['robots']) : $robots;
}
add_filter('wpseo_robots', 'autoagora_filter_wpseo_robots');

function autoagora_filter_rank_math_title($title) {
    $seo = autoagora_get_current_seo_context();

    return !empty($seo['title']) ? $seo['title'] : $title;
}
add_filter('rank_math/frontend/title', 'autoagora_filter_rank_math_title');

function autoagora_filter_rank_math_description($description) {
    $seo = autoagora_get_current_seo_context();

    return !empty($seo['description']) ? $seo['description'] : $description;
}
add_filter('rank_math/frontend/description', 'autoagora_filter_rank_math_description');

function autoagora_filter_rank_math_canonical($canonical) {
    $seo = autoagora_get_current_seo_context();

    return !empty($seo['canonical']) ? $seo['canonical'] : $canonical;
}
add_filter('rank_math/frontend/canonical', 'autoagora_filter_rank_math_canonical');

function autoagora_filter_rank_math_robots($robots) {
    $seo = autoagora_get_current_seo_context();
    if (empty($seo['robots'])) {
        return $robots;
    }

    if (is_array($robots)) {
        if (strpos($seo['robots'], 'noindex') !== false) {
            $robots['index'] = 'noindex';
        } else {
            $robots['index'] = 'index';
        }
        $robots['follow'] = strpos($seo['robots'], 'nofollow') !== false ? 'nofollow' : 'follow';

        return $robots;
    }

    return str_replace(' ', '', $seo['robots']);
}
add_filter('rank_math/frontend/robots', 'autoagora_filter_rank_math_robots');

function autoagora_output_managed_seo_meta() {
    $seo = autoagora_get_current_seo_context();
    if (empty($seo)) {
        return;
    }

    if (!empty($seo['description'])) {
        echo '<meta name="description" content="' . esc_attr($seo['description']) . '">' . "\n";
    }

    if (!empty($seo['canonical'])) {
        echo '<link rel="canonical" href="' . esc_url($seo['canonical']) . '">' . "\n";
    }

    if (!empty($seo['robots'])) {
        echo '<meta name="robots" content="' . esc_attr($seo['robots']) . '">' . "\n";
    }

    $faq_schema = autoagora_get_current_faq_schema();
    if ($faq_schema) {
        echo '<script type="application/ld+json">' . $faq_schema . '</script>' . "\n";
    }
}
add_action('wp_head', 'autoagora_output_managed_seo_meta', 1);

function autoagora_filter_wp_robots($robots) {
    $seo = autoagora_get_current_seo_context();
    if (empty($seo['robots'])) {
        return $robots;
    }

    if (strpos($seo['robots'], 'noindex') !== false) {
        unset($robots['index']);
        $robots['noindex'] = true;
    } else {
        unset($robots['noindex']);
        $robots['index'] = true;
    }

    if (strpos($seo['robots'], 'nofollow') !== false) {
        unset($robots['follow']);
        $robots['nofollow'] = true;
    } else {
        unset($robots['nofollow']);
        $robots['follow'] = true;
    }

    return $robots;
}
add_filter('wp_robots', 'autoagora_filter_wp_robots');

function autoagora_disable_default_canonical_on_custom_routes() {
    if (autoagora_is_cars_filter_route() || is_tax('car_make')) {
        remove_action('wp_head', 'rel_canonical');
    }
}
add_action('wp', 'autoagora_disable_default_canonical_on_custom_routes');

function autoagora_filter_car_make_landing_template($template) {
    if (!is_tax('car_make')) {
        return $template;
    }

    $landing_template = get_stylesheet_directory() . '/taxonomy-car_make-landing.php';

    return file_exists($landing_template) ? $landing_template : $template;
}
add_filter('template_include', 'autoagora_filter_car_make_landing_template', 99);

function autoagora_enqueue_car_make_landing_assets() {
    if (!is_tax('car_make')) {
        return;
    }

    $path = get_stylesheet_directory() . '/assets/css/car-make-landing.css';
    $url = get_stylesheet_directory_uri() . '/assets/css/car-make-landing.css';

    if (file_exists($path)) {
        wp_enqueue_style(
            'autoagora-car-make-landing',
            $url,
            array('bricks-child-theme-css'),
            filemtime($path)
        );
    }
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_car_make_landing_assets', 20);

function autoagora_filter_body_classes($classes) {
    if (is_tax('car_make')) {
        $classes[] = 'autoagora-car-make-landing';
    }

    if (autoagora_is_cars_filter_route()) {
        $classes[] = 'autoagora-cars-filter-route';
    }

    return $classes;
}
add_filter('body_class', 'autoagora_filter_body_classes');

/**
 * Bricks header/footer + custom cars listing body (/cars/, filter routes, car_make taxonomy).
 * Used to skip duplicate Font Awesome and trim plugin CSS.
 *
 * @return bool
 */
function autoagora_is_cars_browse_light_context() {
    if (function_exists('autoagora_is_city_cars_landing_template') && autoagora_is_city_cars_landing_template()) {
        return true;
    }
    if (is_tax('car_make')) {
        return true;
    }
    if (function_exists('autoagora_is_cars_filter_route') && autoagora_is_cars_filter_route()) {
        return true;
    }
    if (is_page()) {
        global $post;
        if ($post && isset($post->post_name) && $post->post_name === 'cars') {
            return true;
        }
    }

    return false;
}
