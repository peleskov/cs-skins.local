@php
$winners = [
    ['rarity' => 'industrial', 'skin' => '520032493', 'avatar' => '179baf06c2dd781e19fb12d19de03052eddc7e50', 'username' => 'ProGamer'],
    ['rarity' => 'milspec', 'skin' => '360467259', 'avatar' => '15fcdaefbbed6e159921f965fc3901cd2994ccec', 'username' => 'SkinsHunter'],
    ['rarity' => 'restricted', 'skin' => '520032493', 'avatar' => '7243274e70b63320a9763023778766124b7547c2', 'username' => 'CsgoBeast'],
    ['rarity' => 'classified', 'skin' => '721076282', 'avatar' => 'e69d4d55395f6871fd12611ba1bdca93885bd5a9', 'username' => 'KnifeMaster'],
    ['rarity' => 'covert', 'skin' => '506853367', 'avatar' => '5de11548671f83e4290f20a0ed2279c9f4b7d099', 'username' => 'SkinCollector'],
    ['rarity' => 'contraband', 'skin' => '2077639777', 'avatar' => 'c4e0c2389e070fff609c6d89d38d359b936bf28e', 'username' => 'GloveGod'],
    ['rarity' => 'industrial', 'skin' => '310781313', 'avatar' => 'a59d10cb637f18f69e9798e462a9b36313fabd86', 'username' => 'RussianBear'],
    ['rarity' => 'milspec', 'skin' => '310801152', 'avatar' => 'f29b44c5636123b4cc3ed5c8dd1cba86be135782', 'username' => 'AWPerPro'],
    ['rarity' => 'restricted', 'skin' => '1310006695', 'avatar' => '179baf06c2dd781e19fb12d19de03052eddc7e50', 'username' => 'CaseOpener'],
    ['rarity' => 'classified', 'skin' => '2735547138', 'avatar' => '15fcdaefbbed6e159921f965fc3901cd2994ccec', 'username' => 'DriveStyler'],
    ['rarity' => 'covert', 'skin' => '310781367', 'avatar' => '7243274e70b63320a9763023778766124b7547c2', 'username' => 'HeadShooter'],
    ['rarity' => 'contraband', 'skin' => '470391537', 'avatar' => 'e69d4d55395f6871fd12611ba1bdca93885bd5a9', 'username' => 'KnifeCollector'],
    ['rarity' => 'industrial', 'skin' => '992174037', 'avatar' => '5de11548671f83e4290f20a0ed2279c9f4b7d099', 'username' => 'SilentKiller'],
    ['rarity' => 'milspec', 'skin' => '1703245053', 'avatar' => 'f29b44c5636123b4cc3ed5c8dd1cba86be135782', 'username' => 'EcoRounder'],
    ['rarity' => 'restricted', 'skin' => '1011981595', 'avatar' => 'c4e0c2389e070fff609c6d89d38d359b936bf28e', 'username' => 'RushMaster'],
    ['rarity' => 'classified', 'skin' => '2736581484', 'avatar' => 'a59d10cb637f18f69e9798e462a9b36313fabd86', 'username' => 'MotoBiker'],
];
@endphp

<section class="carousel-winner pt-2 mb-60">
    <div class="container-fluid">
        <div class="swiper">
            <div class="swiper-wrapper">
                @foreach($winners as $winner)
                <div class="swiper-slide">
                    <div class="item d-flex align-items-center justify-content-center rarity-{{ $winner['rarity'] }}">
                        <div class="image" style="background-image: url(https://community.cloudflare.steamstatic.com/economy/image/class/730/{{ $winner['skin'] }}/256x128);"></div>
                        <div class="user d-flex flex-column align-items-start">
                            <img src="https://avatars.steamstatic.com/{{ $winner['avatar'] }}_medium.jpg" alt="">
                            <p>{{ $winner['username'] }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
