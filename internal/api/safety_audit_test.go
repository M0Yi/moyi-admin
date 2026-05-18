package api

import (
	"go/ast"
	"go/parser"
	"go/token"
	"os"
	"strings"
	"testing"
)

func TestAgentWeChatRuntimePathsDoNotCallFullInstallStateSave(t *testing.T) {
	runtimeFunctions := map[string]struct{}{
		"agentWeChatSession":               {},
		"exchangeAgentWeChatPair":          {},
		"agentWeChatMessage":               {},
		"runAgentWeChatChannelPollOnce":    {},
		"handleAgentWeChatProviderMessage": {},
		"updateAgentWeChatWorkerError":     {},
	}
	fileSet := token.NewFileSet()
	file, err := parser.ParseFile(fileSet, "agent_channel.go", nil, 0)
	if err != nil {
		t.Fatalf("parse agent_channel.go: %v", err)
	}
	for _, decl := range file.Decls {
		fn, ok := decl.(*ast.FuncDecl)
		if !ok || fn.Body == nil {
			continue
		}
		if _, shouldAudit := runtimeFunctions[fn.Name.Name]; !shouldAudit {
			continue
		}
		ast.Inspect(fn.Body, func(node ast.Node) bool {
			call, ok := node.(*ast.CallExpr)
			if !ok {
				return true
			}
			selector, ok := call.Fun.(*ast.SelectorExpr)
			if !ok || selector.Sel.Name != "Save" {
				return true
			}
			t.Fatalf("%s must use narrow runtime store updates, not full install state Save at %s", fn.Name.Name, fileSet.Position(selector.Pos()))
			return true
		})
	}
}

func TestAgentWeChatRuntimeUpdateDoesNotWriteAuthorizationColumns(t *testing.T) {
	source, err := os.ReadFile("metadata_store.go")
	if err != nil {
		t.Fatalf("read metadata_store.go: %v", err)
	}
	fileSet := token.NewFileSet()
	file, err := parser.ParseFile(fileSet, "metadata_store.go", source, 0)
	if err != nil {
		t.Fatalf("parse metadata_store.go: %v", err)
	}
	for _, decl := range file.Decls {
		fn, ok := decl.(*ast.FuncDecl)
		if !ok || fn.Name.Name != "updateAgentWeChatRuntimeRows" {
			continue
		}
		body := string(source[fileSet.Position(fn.Body.Pos()).Offset:fileSet.Position(fn.Body.End()).Offset])
		for _, forbidden := range []string{"data_scope", "allowed_tables"} {
			if strings.Contains(body, forbidden) {
				t.Fatalf("runtime channel SQL must not write authorization column %q", forbidden)
			}
		}
		return
	}
	t.Fatal("updateAgentWeChatRuntimeRows not found")
}
