# language: pt
Funcionalidade: Saga da ordem de serviço entre OS, Billing e Execution
  Como oficina com várias filiais
  Quero que abertura, orçamento, aprovação, execução e pagamento de uma OS sejam consistentes entre os microsserviços
  Para que a falha de qualquer etapa desfaça com segurança as etapas anteriores

  Contexto:
    Dado uma peça "Pastilha de freio" de R$ 115.00 com 5 unidades em estoque

  Cenário: Fluxo completo de uma OS até a entrega
    Quando a recepção abre uma OS com 2 unidades de "Pastilha de freio"
    Então o comando "cmd.execution.enqueue_diagnosis" é enviado
    Quando o Execution responde "evt.execution.diagnosis_enqueued"
    E o Execution responde "evt.execution.diagnosis_started"
    Então a OS fica com status "in_diagnosis"
    Quando o Execution responde "evt.execution.diagnosis_completed"
    Então o comando "cmd.billing.create_budget" é enviado com total de R$ 230.00
    Quando o Billing responde que o orçamento de R$ 230.00 foi criado
    Então a OS fica com status "awaiting_approval"
    Quando o cliente aprova o orçamento no Billing
    Então o estoque de "Pastilha de freio" fica com 3 unidades
    E o comando "cmd.execution.enqueue_repair" é enviado
    Quando o Execution responde "evt.execution.repair_enqueued"
    Então a OS fica com status "approved"
    Quando o Execution responde "evt.execution.repair_started"
    E o Execution responde "evt.execution.repair_finished"
    Então a OS fica com status "finalized"
    E o comando "cmd.billing.request_payment" é enviado
    Quando o Billing responde "evt.billing.payment_requested"
    E o Billing responde "evt.billing.payment_confirmed"
    E a recepção entrega o veículo
    Então a OS fica com status "delivered"
    E a saga fica com status "completed"

  Cenário: Estoque insuficiente na aprovação compensa orçamento e tarefa de execução
    Quando a recepção abre uma OS com 8 unidades de "Pastilha de freio"
    E a OS avança até o orçamento aguardando aprovação
    E o cliente aprova o orçamento no Billing
    Então a OS fica com status "cancelled"
    E a saga fica com status "compensated"
    E o comando "cmd.billing.void_budget" é enviado
    E o comando "cmd.execution.cancel_task" é enviado
    E o estoque de "Pastilha de freio" fica com 5 unidades

  Cenário: Falha ao enfileirar o reparo devolve o estoque reservado
    Quando a recepção abre uma OS com 2 unidades de "Pastilha de freio"
    E a OS avança até o orçamento aguardando aprovação
    E o cliente aprova o orçamento no Billing
    Então o estoque de "Pastilha de freio" fica com 3 unidades
    Quando o Execution recusa com "evt.execution.repair_enqueue_failed" pelo motivo "fila de execução indisponível"
    Então a OS fica com status "cancelled"
    E o estoque de "Pastilha de freio" fica com 5 unidades
    E o motivo da compensação contém "fila de execução indisponível"

  Cenário: Cliente rejeita o orçamento
    Quando a recepção abre uma OS com 1 unidades de "Pastilha de freio"
    E a OS avança até o orçamento aguardando aprovação
    E o Billing responde "evt.billing.budget_rejected"
    Então a OS fica com status "cancelled"
    E o comando "cmd.billing.void_budget" é enviado

  Cenário: Execution fora do ar — o watchdog reenvia e depois compensa
    Quando a recepção abre uma OS com 1 unidades de "Pastilha de freio"
    E o Execution fica sem responder enquanto o watchdog roda 3 vezes
    Então o comando "cmd.execution.enqueue_diagnosis" foi enviado 3 vezes
    E a OS fica com status "cancelled"
    E o motivo da compensação contém "timeout no passo enqueue_diagnosis"
