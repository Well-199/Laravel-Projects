<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserAppointment;
use App\Models\UserFavorite;
use App\Models\Barber;
use App\Models\BarberPhotos;
use App\Models\BarberServices;
use App\Models\BarberTestimonial;
use App\Models\BarberAvailability;

class BarberController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    private function searchGeo($address)
    {
        $key = env('MAPS_KEY', null);

        $address = urlencode($address);

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.$address.'&key='.$key;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOP_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);

        return json_decode($res, true);
    }

    public function list(Request $request)
    {
        $array = ['error' => ''];

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $city = $request->input('city');
        $offset = $request->input('offset');

        if(!$offset)
        {
            $offset = 0;
        }

        if(!empty($city))
        {
            // $res recebe o resultado ou retorno da execução da função searchGeo
            $res = $this->searchGeo($city);

            if(count($res['results']) > 0)
            {
                $lat = $res['results'][0]['geometry']['location']['lat'];
                $lng = $res['results'][0]['geometry']['location']['lng'];
            }
        } elseif (!empty($lat) && !empty($lng)) {

            $res = $this->searchGeo($lat.','.$lng);

            if(count($res['results']) > 0)
            {
                $city = $res['results'][0]['formatted_address'];
            }
        } else {

            $lat = '-23.5930907';
            $lng = '-46.6182795';
            $city = 'São Paulo';
        }

        $barbers = Barber::select('*', Barber::raw('SQRT(
            POW(69.1 * (latitude - '.$lat.'), 2) +
            POW(69.1 * ('.$lng.' - longitude) * COS(latitude / 57.3), 2)) AS distance'))
            ->whereRaw('SQRT(
            POW(69.1 * (latitude - '.$lat.'), 2) +
            POW(69.1 * ('.$lng.' - longitude) * COS(latitude / 57.3), 2)) < ?', [10])
            ->orderBy('distance', 'ASC')
            ->offset($offset)
            ->limit(5)
            ->get();

        foreach($barbers as $bkey => $bvalue)
        {
            $barbers[$bkey]['avatar'] = url('media/avatars/'.$barbers[$bkey]['avatar']);
        }

        $array['data'] = $barbers;
        $array['loc'] = 'São Paulo';

        return $array;
    }

    public function one($id)
    {
        $array = ['error' => ''];

        $barber = Barber::find($id);

        if($barber)
        {
            $barber['avatar'] = url('media/avatars/'.$barber['avatar']);
            $barber['favorited'] = false;
            $barber['photos'] = [];
            $barber['services'] = [];
            $barber['testimonials'] = [];
            $barber['available'] = [];

            // Pegando as fotos do barbeiro
            $barber['photos'] = BarberPhotos::select(['id', 'url'])->where('id_barber', $barber->id)->get();

            foreach($barber['photos'] as $bpkey => $bpvalue)
            {
                $barber['photos'][$bpkey]['url'] = url('media/uploads/'.$barber['photos'][$bpkey]['url']);
            }

            // Pegando os servicos do barbeiro
            $barber['services'] = BarberServices::select(['id', 'name', 'price'])
                ->where('id_barber', $barber->id)
                ->get();

            // Pegando os depoimentos do barbeiro
            $barber['testimonials'] = BarberTestimonial::select(['id', 'name', 'rate', 'body'])
                ->where('id_barber', $barber->id)
                ->get();

            // Pegando os horarios disponiveis do barbeiro




            $array['data'] = $barber;

        } else {
            $array['error'] = 'Barbeiro não existe /ou id invalido';
            return $array;
        }


        return $array;
    }

}
