PORTREVISION=	1
CATEGORIES=	graphics

COMMENT=	GStreamer OpenExr codec plugin

LIB_DEPENDS=	libOpenEXR.so:graphics/openexr \
		libImath-3_1.so:math/Imath

USES+=		compiler:c++11-lang

CPPFLAGS+=	-DInt64=uint64_t

MASTERDIR=	${.CURDIR:H:H}/multimedia/gstreamer1-plugins

PLIST=		${.CURDIR}/pkg-plist

DIST=		bad
GST_PLUGIN=	openexr

.include "${MASTERDIR}/Makefile"
