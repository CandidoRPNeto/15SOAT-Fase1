<?php

namespace Database\Seeders;

use App\Enums\ServiceOrderStatus;
use App\Enums\UserRole;
use App\Models\Part;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPart;
use App\Models\ServiceOrderService;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------------------------------------
        // Usuários (credenciais fixas para demo)
        // ---------------------------------------------------------------
        User::create([
            'name' => 'Ana Recepcionista',
            'email' => 'recepcao@workshop.com',
            'password' => Hash::make('password'),
            'role' => UserRole::RECEPTIONIST,
            'cpf_cnpj' => '000.000.000-01',
            'phone' => '(11) 99999-0001',
        ]);

        User::create([
            'name' => 'João Mecânico',
            'email' => 'mecanico@workshop.com',
            'password' => Hash::make('password'),
            'role' => UserRole::MECHANIC,
            'cpf_cnpj' => '000.000.000-02',
            'phone' => '(11) 99999-0002',
        ]);

        $clients = collect([
            User::create([
                'name' => 'Carlos Silva',
                'email' => 'carlos@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::CLIENT,
                'cpf_cnpj' => '111.111.111-11',
                'phone' => '(11) 98888-0001',
            ]),
            User::create([
                'name' => 'Maria Oliveira',
                'email' => 'maria@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::CLIENT,
                'cpf_cnpj' => '222.222.222-22',
                'phone' => '(11) 98888-0002',
            ]),
            User::create([
                'name' => 'Pedro Santos',
                'email' => 'pedro@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::CLIENT,
                'cpf_cnpj' => '333.333.333-33',
                'phone' => '(11) 98888-0003',
            ]),
        ]);

        // ---------------------------------------------------------------
        // Veículos (2 por cliente)
        // ---------------------------------------------------------------
        $vehiclesRaw = [
            ['Toyota',     'Corolla', 'ABC-1234', 2022, 'Prata',    $clients[0]->id],
            ['Honda',      'Civic',   'DEF-5678', 2021, 'Preto',    $clients[0]->id],
            ['Volkswagen', 'Gol',     'GHI-9012', 2019, 'Branco',   $clients[1]->id],
            ['Chevrolet',  'Onix',    'JKL-3456', 2023, 'Vermelho', $clients[1]->id],
            ['Ford',       'Ka',      'MNO-7890', 2020, 'Azul',     $clients[2]->id],
            ['Hyundai',    'HB20',    'PQR-2345', 2022, 'Cinza',    $clients[2]->id],
        ];

        $vehicles = collect($vehiclesRaw)->map(fn ($d) => Vehicle::create([
            'client_id' => $d[5],
            'brand'     => $d[0],
            'model'     => $d[1],
            'plate'     => $d[2],
            'year'      => $d[3],
            'color'     => $d[4],
        ]));

        // ---------------------------------------------------------------
        // Serviços
        // ---------------------------------------------------------------
        $servicesRaw = [
            ['Troca de óleo',                   120.00,  60, 'Troca de óleo do motor com filtro'],
            ['Alinhamento de rodas',              80.00,  45, 'Alinhamento eletrônico de 4 rodas'],
            ['Balanceamento de pneus',            60.00,  30, 'Balanceamento estático e dinâmico'],
            ['Revisão completa 50.000 km',       350.00, 240, 'Revisão completa com troca de fluidos'],
            ['Troca de pastilhas de freio',      200.00,  90, 'Freios dianteiros e traseiros'],
            ['Diagnóstico eletrônico',           150.00,  60, 'Scanner OBD2 completo'],
            ['Troca de correia dentada',         280.00, 120, 'Correia + tensor + bomba d\'água'],
            ['Troca de filtro de ar',             50.00,  15, 'Filtro de ar do motor'],
            ['Troca de velas de ignição',        130.00,  40, 'Jogo de 4 velas iridium'],
            ['Higienização do ar condicionado',   90.00,  60, 'Higienização completa do sistema A/C'],
        ];

        $services = collect($servicesRaw)->map(fn ($s) => Service::create([
            'name'                  => $s[0],
            'price'                 => $s[1],
            'avg_execution_minutes' => $s[2],
            'description'           => $s[3],
            'active'                => true,
        ]));

        // ---------------------------------------------------------------
        // Peças
        // ---------------------------------------------------------------
        $partsRaw = [
            ['Filtro de óleo Mann',                 'FO-001',  35.00, 50],
            ['Pastilha de freio dianteira Bosch',   'PFD-001', 89.00, 30],
            ['Filtro de ar Mann',                   'FA-001',  45.00, 40],
            ['Vela de ignição NGK Iridium',         'VI-001',  28.50, 80],
            ['Correia dentada Gates',               'CD-001', 120.00, 15],
            ['Tensor de correia',                   'TC-001',  85.00, 12],
            ['Bomba d\'água',                       'BA-001', 180.00,  8],
            ['Pastilha de freio traseira Bosch',    'PFT-001', 75.00, 25],
            ['Óleo de motor 5W30 sintético (1L)',   'OM-001',  32.00, 100],
            ['Filtro de combustível',               'FC-001',  55.00, 35],
            ['Vela de ignição NGK standard',        'VS-001',  18.00, 60],
            ['Correia do alternador',               'CA-001',  45.00, 20],
            ['Fluido de freio DOT4 (500ml)',        'FF-001',  22.00, 45],
            ['Filtro de cabine / ar condicionado',  'FAC-001', 38.00, 30],
            ['Disco de freio dianteiro',            'DFD-001', 145.00, 10],
        ];

        $parts = collect($partsRaw)->map(fn ($p) => Part::create([
            'name'           => $p[0],
            'part_number'    => $p[1],
            'price'          => $p[2],
            'stock_quantity' => $p[3],
            'description'    => 'Peça original de reposição',
            'active'         => true,
        ]));

        // ---------------------------------------------------------------
        // Ordens de serviço — uma por status do fluxo
        // ---------------------------------------------------------------

        // 1. received
        ServiceOrder::create([
            'client_id'  => $clients[0]->id,
            'vehicle_id' => $vehicles[0]->id,
            'status'     => ServiceOrderStatus::RECEIVED,
            'notes'      => 'Cliente relatou barulho no motor ao ligar a frio.',
        ]);

        // 2. in_diagnosis
        ServiceOrder::create([
            'client_id'  => $clients[0]->id,
            'vehicle_id' => $vehicles[1]->id,
            'status'     => ServiceOrderStatus::IN_DIAGNOSIS,
            'notes'      => 'Freios rangendo ao frear em baixa velocidade.',
        ]);

        // 3. awaiting_approval
        $os3 = ServiceOrder::create([
            'client_id'       => $clients[1]->id,
            'vehicle_id'      => $vehicles[2]->id,
            'status'          => ServiceOrderStatus::AWAITING_APPROVAL,
            'notes'           => 'Revisão dos 50.000 km conforme manual.',
            'budget_sent_at'  => now()->subHours(3),
        ]);
        $this->addService($os3, $services[3], 1);
        $this->addService($os3, $services[0], 1);
        $this->addPart($os3, $parts[0], 1);
        $this->addPart($os3, $parts[2], 1);
        $this->addPart($os3, $parts[8], 4);
        $os3->update(['total_amount' => $os3->refresh()->calculateTotal()]);

        // 4. approved
        $os4 = ServiceOrder::create([
            'client_id'      => $clients[1]->id,
            'vehicle_id'     => $vehicles[3]->id,
            'status'         => ServiceOrderStatus::APPROVED,
            'notes'          => 'Troca de correia dentada aprovada pelo cliente.',
            'budget_sent_at' => now()->subDay(),
        ]);
        $this->addService($os4, $services[6], 1);
        $this->addPart($os4, $parts[4], 1);
        $this->addPart($os4, $parts[5], 1);
        $this->addPart($os4, $parts[6], 1);
        $os4->update(['total_amount' => $os4->refresh()->calculateTotal()]);

        // 5. cancelled
        $os5 = ServiceOrder::create([
            'client_id'      => $clients[2]->id,
            'vehicle_id'     => $vehicles[4]->id,
            'status'         => ServiceOrderStatus::CANCELLED,
            'notes'          => 'Cliente cancelou após receber o orçamento.',
            'budget_sent_at' => now()->subDays(2),
        ]);
        $this->addService($os5, $services[1], 1);
        $this->addPart($os5, $parts[1], 1);
        $os5->update(['total_amount' => $os5->refresh()->calculateTotal()]);

        // 6. in_execution
        $os6 = ServiceOrder::create([
            'client_id'      => $clients[2]->id,
            'vehicle_id'     => $vehicles[5]->id,
            'status'         => ServiceOrderStatus::IN_EXECUTION,
            'notes'          => 'Troca de pastilhas e discos em execução.',
            'budget_sent_at' => now()->subDay(),
        ]);
        $this->addService($os6, $services[4], 1);
        $this->addPart($os6, $parts[1], 1);
        $this->addPart($os6, $parts[7], 1);
        $this->addPart($os6, $parts[14], 2);
        $os6->update(['total_amount' => $os6->refresh()->calculateTotal()]);

        // 7. finalized (aguardando pagamento)
        $os7 = ServiceOrder::create([
            'client_id'      => $clients[0]->id,
            'vehicle_id'     => $vehicles[0]->id,
            'status'         => ServiceOrderStatus::FINALIZED,
            'notes'          => 'Serviço concluído. Aguardando retirada e pagamento.',
            'budget_sent_at' => now()->subDays(3),
            'finalized_at'   => now()->subHours(2),
        ]);
        $this->addService($os7, $services[5], 1);
        $this->addService($os7, $services[8], 1);
        $this->addPart($os7, $parts[3], 4);
        $os7->update(['total_amount' => $os7->refresh()->calculateTotal()]);

        // 8. delivered
        $os8 = ServiceOrder::create([
            'client_id'      => $clients[1]->id,
            'vehicle_id'     => $vehicles[2]->id,
            'status'         => ServiceOrderStatus::DELIVERED,
            'notes'          => 'Veículo entregue ao cliente após pagamento.',
            'budget_sent_at' => now()->subDays(5),
            'finalized_at'   => now()->subDays(1),
            'paid_at'        => now()->subHours(4),
            'delivered_at'   => now()->subHours(3),
        ]);
        $this->addService($os8, $services[0], 1);
        $this->addService($os8, $services[9], 1);
        $this->addPart($os8, $parts[0], 1);
        $this->addPart($os8, $parts[13], 1);
        $this->addPart($os8, $parts[8], 4);
        $os8->update(['total_amount' => $os8->refresh()->calculateTotal()]);
    }

    private function addService(ServiceOrder $order, Service $service, int $qty): void
    {
        ServiceOrderService::create([
            'service_order_id' => $order->id,
            'service_id'       => $service->id,
            'quantity'         => $qty,
            'unit_price'       => $service->price,
        ]);
    }

    private function addPart(ServiceOrder $order, Part $part, int $qty): void
    {
        ServiceOrderPart::create([
            'service_order_id' => $order->id,
            'part_id'          => $part->id,
            'quantity'         => $qty,
            'unit_price'       => $part->price,
        ]);
    }
}
