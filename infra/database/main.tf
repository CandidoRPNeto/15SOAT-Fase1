# Reaproveita os manifestos em /k8s como única fonte de verdade em vez de
# duplicar os mesmos recursos como HCL — evita definições divergentes do
# mesmo recurso em dois lugares. Além de k8s/postgres.yaml, este módulo
# também aplica k8s/configmap.yaml e k8s/secret.yaml porque o Deployment do
# Postgres lê DB_DATABASE/DB_USERNAME/DB_PASSWORD deles; sem isso, o banco
# não teria como subir num cluster limpo só com este módulo.
#
# yamldecode() não suporta múltiplos documentos YAML nativamente
# (hashicorp/terraform#29729), por isso o split manual por "---". A ordem de
# aplicação entre ConfigMap/Secret e o Deployment do Postgres não é forçada
# aqui — o Kubernetes reconcilia sozinho (o Pod fica pendente até as
# dependências existirem).
locals {
  manifest_files = [
    "${path.module}/../../k8s/configmap.yaml",
    "${path.module}/../../k8s/secret.yaml",
    "${path.module}/../../k8s/postgres.yaml",
  ]

  manifest_documents = flatten([
    for manifest_file in local.manifest_files : [
      for doc in split("\n---\n", file(manifest_file)) :
      yamldecode(doc) if trimspace(doc) != ""
    ]
  ])
}

resource "kubernetes_manifest" "database" {
  for_each = {
    for doc in local.manifest_documents :
    # kubernetes_manifest (ao contrário de `kubectl apply`) não assume o
    # namespace "default" implicitamente — precisa vir explícito no doc.
    "${doc.kind}/${doc.metadata.name}" => merge(doc, {
      metadata = merge(doc.metadata, { namespace = "default" })
    })
  }

  manifest = each.value
}
