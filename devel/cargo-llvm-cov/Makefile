PORTNAME=	cargo-llvm-cov
DISTVERSIONPREFIX=	v
DISTVERSION=	0.6.14
CATEGORIES=	devel

MAINTAINER=	yuri@FreeBSD.org
COMMENT=	Rust: Cargo subcommand to easily use LLVM source-based code coverage
WWW=		https://github.com/taiki-e/cargo-llvm-cov

LICENSE=	APACHE20 MIT
LICENSE_COMB=	dual
LICENSE_FILE_APACHE20=	${WRKSRC}/LICENSE-APACHE
LICENSE_FILE_MIT=	${WRKSRC}/LICENSE-MIT

USES=		cargo

USE_GITHUB=	yes
GH_ACCOUNT=	taiki-e

CARGO_CRATES=	aho-corasick-1.1.3 \
		anyhow-1.0.89 \
		autocfg-1.4.0 \
		bitflags-2.6.0 \
		bstr-1.10.0 \
		camino-1.1.9 \
		cargo-config2-0.1.29 \
		cfg-if-1.0.0 \
		duct-0.13.7 \
		easy-ext-1.0.2 \
		equivalent-1.0.1 \
		errno-0.3.9 \
		fastrand-2.1.1 \
		filetime-0.2.25 \
		fs-err-2.11.0 \
		glob-0.3.1 \
		hashbrown-0.15.0 \
		home-0.5.9 \
		indexmap-2.6.0 \
		is_executable-1.0.4 \
		itoa-1.0.11 \
		lcov2cobertura-1.0.5 \
		lexopt-0.3.0 \
		libc-0.2.159 \
		libredox-0.1.3 \
		linux-raw-sys-0.4.14 \
		memchr-2.7.4 \
		normpath-1.3.0 \
		once_cell-1.20.2 \
		opener-0.7.2 \
		os_pipe-1.2.1 \
		proc-macro2-1.0.87 \
		quick-xml-0.31.0 \
		quote-1.0.37 \
		redox_syscall-0.5.7 \
		regex-1.11.0 \
		regex-automata-0.4.8 \
		regex-syntax-0.8.5 \
		rustc-demangle-0.1.24 \
		rustix-0.38.37 \
		rustversion-1.0.17 \
		ruzstd-0.7.2 \
		ryu-1.0.18 \
		same-file-1.0.6 \
		serde-1.0.210 \
		serde_derive-1.0.210 \
		serde_json-1.0.128 \
		serde_spanned-0.6.8 \
		shared_child-1.0.1 \
		shell-escape-0.1.5 \
		syn-2.0.79 \
		tar-0.4.42 \
		tempfile-3.13.0 \
		termcolor-1.4.1 \
		toml_datetime-0.6.8 \
		toml_edit-0.22.22 \
		unicode-ident-1.0.13 \
		walkdir-2.5.0 \
		winapi-0.3.9 \
		winapi-i686-pc-windows-gnu-0.4.0 \
		winapi-util-0.1.9 \
		winapi-x86_64-pc-windows-gnu-0.4.0 \
		windows-sys-0.52.0 \
		windows-sys-0.59.0 \
		windows-targets-0.52.6 \
		windows_aarch64_gnullvm-0.52.6 \
		windows_aarch64_msvc-0.52.6 \
		windows_i686_gnu-0.52.6 \
		windows_i686_gnullvm-0.52.6 \
		windows_i686_msvc-0.52.6 \
		windows_x86_64_gnu-0.52.6 \
		windows_x86_64_gnullvm-0.52.6 \
		windows_x86_64_msvc-0.52.6 \
		winnow-0.6.20 \
		xattr-1.3.1

PLIST_FILES=	bin/${PORTNAME}

.include <bsd.port.mk>
